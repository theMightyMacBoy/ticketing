<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Exceptions\MailboxMisconfigured;
use Coderflex\LaravelTicket\Exceptions\UnmatchedSender;
use Coderflex\LaravelTicket\Mail\GraphMailboxReader;
use Coderflex\LaravelTicket\Mail\InboundEmail;
use Coderflex\LaravelTicket\Mail\MailboxIntake;
use Coderflex\LaravelTicket\Mail\MailboxReader;
use Coderflex\LaravelTicket\Mail\TicketReplyMail;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\EmailMessage;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Tests\Models\User;
use Coderflex\LaravelTicket\Tests\Support\FakeMailboxReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function sharedDesk(): array
{
    $mailbox = Mailbox::query()->create([
        'name' => 'Help Desk',
        'address' => 'help@contoso.com',
        'tenant_id' => 'tenant-1',
        'client_id' => 'client-1',
        'client_secret' => 'secret-1',
    ]);

    $network = AssignmentGroup::query()->create([
        'name' => 'Network',
        'slug' => 'network',
    ]);
    $desktop = AssignmentGroup::query()->create([
        'name' => 'Desktop',
        'slug' => 'desktop',
    ]);

    $network->connectMailbox($mailbox, true);
    $desktop->connectMailbox($mailbox);

    return [$mailbox, $network->fresh(), $desktop->fresh()];
}

function inboundMail(array $overrides = []): InboundEmail
{
    return new InboundEmail(
        graphId: $overrides['graphId'] ?? 'graph-1',
        internetMessageId: $overrides['internetMessageId'] ?? '<new@contoso.com>',
        inReplyTo: $overrides['inReplyTo'] ?? null,
        references: $overrides['references'] ?? '',
        fromAddress: $overrides['fromAddress'] ?? 'ada@contoso.com',
        fromName: $overrides['fromName'] ?? 'Ada Lovelace',
        subject: $overrides['subject'] ?? 'Printer jam',
        body: $overrides['body'] ?? 'Paper jam on floor 2',
        receivedAt: Carbon::parse($overrides['receivedAt'] ?? '2026-10-25 12:00:00'),
    );
}

it('opens new mail on the primary group when several groups share an address', function () {
    [$mailbox, $network, $desktop] = sharedDesk();
    User::factory()->create(['email' => 'ada@contoso.com']);

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail()];
    app()->instance(MailboxReader::class, $reader);
    Mail::fake();

    expect(app(MailboxIntake::class)->ingest($mailbox))->toBe(1);

    $ticket = Ticket::query()->sole();

    expect($ticket->assignment_group_id)->toBe($network->id)
        ->and($ticket->channel)->toBe('email')
        ->and($ticket->mailbox_id)->toBe($mailbox->id)
        ->and($ticket->requester_email)->toBe('ada@contoso.com')
        ->and($ticket->title)->toBe('Printer jam')
        ->and($desktop->tickets()->count())->toBe(0)
        ->and(EmailMessage::query()->where('graph_id', 'graph-1')->exists())->toBeTrue()
        ->and($reader->marked)->toBe(['graph-1'])
        ->and($ticket->first_response_at)->toBeNull();

    Mail::assertNothingSent();
});

it('links a reply to the existing ticket and leaves the assignment group alone', function () {
    [$mailbox, $network, $desktop] = sharedDesk();
    User::factory()->create(['email' => 'ada@contoso.com']);

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail()];
    app()->instance(MailboxReader::class, $reader);
    Mail::fake();

    app(MailboxIntake::class)->ingest($mailbox);

    $ticket = Ticket::query()->sole();
    $ticket->assignGroup($desktop);

    $reader->messages = [inboundMail([
        'graphId' => 'graph-2',
        'internetMessageId' => '<reply@contoso.com>',
        'inReplyTo' => '<new@contoso.com>',
        'references' => '<new@contoso.com>',
        'subject' => 'Re: Printer jam',
        'body' => 'It jammed again',
    ])];

    expect(app(MailboxIntake::class)->ingest($mailbox->fresh()))->toBe(1);

    $ticket->refresh();
    $reply = $ticket->messages()->sole();

    expect(Ticket::query()->count())->toBe(1)
        ->and($ticket->assignment_group_id)->toBe($desktop->id)
        ->and($ticket->mailbox_id)->toBe($mailbox->id)
        ->and($reply->message)->toBe('It jammed again')
        ->and($reply->sender_email)->toBe('ada@contoso.com')
        ->and($reply->visibility)->toBe('public')
        ->and($ticket->first_response_at)->toBeNull()
        ->and($reader->marked)->toBe(['graph-1', 'graph-2']);

    Mail::assertNothingSent();
});

it('links a reply from the ticket number in the subject', function () {
    [$mailbox] = sharedDesk();
    $customer = User::factory()->create(['email' => 'ada@contoso.com']);

    $ticket = app(TicketDesk::class)->open([
        'title' => 'Printer jam',
        'user_id' => $customer->id,
        'requester_email' => 'ada@contoso.com',
        'mailbox_id' => $mailbox->id,
        'assignment_group_id' => $mailbox->primaryGroup()?->getKey(),
    ], $customer);

    EmailMessage::query()->create([
        'mailbox_id' => $mailbox->id,
        'ticket_id' => $ticket->id,
        'direction' => 'outbound',
        'internet_message_id' => 'outbound@contoso.com',
        'from_address' => 'help@contoso.com',
        'subject' => "[{$ticket->ticket_number}] Printer jam",
    ]);

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail([
        'subject' => 'Re: ['.strtolower((string) $ticket->ticket_number).'] still broken',
        'internetMessageId' => '<followup@contoso.com>',
    ])];
    app()->instance(MailboxReader::class, $reader);
    Mail::fake();

    expect(app(MailboxIntake::class)->ingest($mailbox))->toBe(1)
        ->and(Ticket::query()->count())->toBe(1)
        ->and($ticket->fresh()->messages()->count())->toBe(1);
});

it('does not open a second ticket when the same graph message is fetched again', function () {
    [$mailbox] = sharedDesk();
    User::factory()->create(['email' => 'ada@contoso.com']);

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail()];
    app()->instance(MailboxReader::class, $reader);

    $intake = app(MailboxIntake::class);

    expect($intake->ingest($mailbox))->toBe(1)
        ->and($intake->ingest($mailbox->fresh()))->toBe(0)
        ->and(Ticket::query()->count())->toBe(1)
        ->and($reader->marked)->toBe(['graph-1', 'graph-1']);
});

it('sends every shared group reply as the shared mailbox address', function () {
    [$mailbox, $network, $desktop] = sharedDesk();
    $customer = User::factory()->create(['email' => 'ada@contoso.com']);
    $agent = User::factory()->create();

    $ticket = app(TicketDesk::class)->open([
        'title' => 'Printer jam',
        'message' => 'Paper jam on floor 2',
        'user_id' => $customer->id,
        'requester_email' => 'ada@contoso.com',
        'mailbox_id' => $mailbox->id,
        'assignment_group_id' => $network->id,
        'channel' => 'email',
    ], $customer);

    $ticket->assignGroup($desktop);

    EmailMessage::query()->create([
        'mailbox_id' => $mailbox->id,
        'ticket_id' => $ticket->id,
        'direction' => 'inbound',
        'internet_message_id' => 'customer-msg@contoso.com',
        'from_address' => 'ada@contoso.com',
        'subject' => 'Printer jam',
    ]);

    Mail::fake();

    $ticket->messageAsUser($agent, 'We cleared the jam.');
    $ticket->note('Ordered a roller.', $agent);

    Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) use ($ticket) {
        $mail->assertSeeInText((string) $ticket->ticket_number);
        $mail->assertSeeInText('Reply to this email');

        return $mail->fromAddress === 'help@contoso.com'
            && $mail->fromName === 'Help Desk'
            && $mail->hasTo('ada@contoso.com')
            && str_contains($mail->subjectLine, (string) $ticket->ticket_number);
    });

    Mail::assertSentCount(1);

    $outbound = EmailMessage::query()->where('direction', 'outbound')->sole();

    expect($outbound->from_address)->toBe('help@contoso.com')
        ->and($outbound->in_reply_to)->toBe('customer-msg@contoso.com')
        ->and($ticket->fresh()->first_response_at)->not->toBeNull()
        ->and($ticket->fresh()->assignment_group_id)->toBe($desktop->id);
});

it('skips mail the mailbox sent to itself', function () {
    [$mailbox] = sharedDesk();

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail(['fromAddress' => 'help@contoso.com'])];
    app()->instance(MailboxReader::class, $reader);

    expect(app(MailboxIntake::class)->ingest($mailbox))->toBe(0)
        ->and(Ticket::query()->count())->toBe(0)
        ->and($reader->marked)->toBe(['graph-1']);
});

it('refuses an unknown sender until a system user is configured', function () {
    [$mailbox] = sharedDesk();

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail(['fromAddress' => 'stranger@contoso.com'])];
    app()->instance(MailboxReader::class, $reader);

    expect(fn () => app(MailboxIntake::class)->ingest($mailbox))->toThrow(UnmatchedSender::class);
    expect(Ticket::query()->count())->toBe(0)
        ->and($reader->marked)->toBe([]);

    $system = User::factory()->create();
    config()->set('laravel_ticket.mail.system_user_id', $system->id);

    expect(app(MailboxIntake::class)->ingest($mailbox->fresh()))->toBe(1);

    $ticket = Ticket::query()->sole();

    expect($ticket->user_id)->toBe($system->id)
        ->and($ticket->requester_email)->toBe('stranger@contoso.com');
});

it('keeps one primary group per shared mailbox', function () {
    $mailbox = Mailbox::query()->create([
        'name' => 'Help Desk',
        'address' => 'help@contoso.com',
    ]);

    $network = AssignmentGroup::query()->create(['name' => 'Network', 'slug' => 'network']);
    $desktop = AssignmentGroup::query()->create(['name' => 'Desktop', 'slug' => 'desktop']);

    $network->connectMailbox($mailbox, false);

    expect($network->fresh()->is_mailbox_primary)->toBeTrue();

    $desktop->connectMailbox($mailbox, true);

    expect($network->fresh()->is_mailbox_primary)->toBeFalse()
        ->and($desktop->fresh()->is_mailbox_primary)->toBeTrue();

    $network->fresh()->makePrimary();

    expect($network->fresh()->is_mailbox_primary)->toBeTrue()
        ->and($desktop->fresh()->is_mailbox_primary)->toBeFalse();
});

it('reads unread mail with application credentials and marks it read', function () {
    [$mailbox, $network] = sharedDesk();
    User::factory()->create(['email' => 'ada@contoso.com']);

    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response([
            'access_token' => 'token-1',
            'expires_in' => 3600,
        ]),
        'https://graph.microsoft.com/*/users/*/mailFolders/inbox/messages*' => Http::response([
            'value' => [[
                'id' => 'graph-9',
                'subject' => 'Re: Badge printer',
                'body' => ['contentType' => 'text', 'content' => 'The badge printer is offline'],
                'from' => ['emailAddress' => ['address' => 'ada@contoso.com', 'name' => 'Ada']],
                'receivedDateTime' => '2026-10-25T16:00:00Z',
                'internetMessageId' => '<abc@contoso.com>',
                'internetMessageHeaders' => [
                    ['name' => 'In-Reply-To', 'value' => '<parent@contoso.com>'],
                    ['name' => 'References', 'value' => '<root@contoso.com> <parent@contoso.com>'],
                ],
                'isRead' => false,
            ]],
        ]),
        'https://graph.microsoft.com/*/users/*/messages/*' => Http::response('', 200),
    ]);

    $reader = app(GraphMailboxReader::class);
    $fetched = $reader->fetch($mailbox);
    $reader->fetch($mailbox->fresh());

    expect($fetched)->toHaveCount(1)
        ->and($fetched[0]->referenceIds())->toBe(['parent@contoso.com', 'root@contoso.com'])
        ->and($fetched[0]->fromName)->toBe('Ada');

    Http::assertSentCount(3);

    expect(app(MailboxIntake::class)->ingest($mailbox->fresh()))->toBe(1);

    $ticket = Ticket::query()->sole();

    expect($ticket->assignment_group_id)->toBe($network->id)
        ->and(EmailMessage::query()->sole()->in_reply_to)->toBe('parent@contoso.com')
        ->and(EmailMessage::query()->sole()->internet_message_id)->toBe('abc@contoso.com');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'tenant-1')
            && $request['grant_type'] === 'client_credentials'
            && $request['scope'] === 'https://graph.microsoft.com/.default'
            && $request['client_id'] === 'client-1';
    });

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), 'inbox/messages')
            && str_contains(urldecode($request->url()), 'isRead eq false');
    });

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains(urldecode($request->url()), 'graph-9')
            && $request['isRead'] === true;
    });
});

it('reports a graph token failure without the client secret', function () {
    [$mailbox] = sharedDesk();

    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'secret-1 was rejected',
        ], 401),
    ]);

    try {
        app(GraphMailboxReader::class)->fetch($mailbox);
        $this->fail('The token request should have failed.');
    } catch (MailboxMisconfigured $exception) {
        expect($exception->getMessage())->toContain('invalid_client')
            ->and($exception->getMessage())->not->toContain('secret-1');
    }
});

it('fails the fetch command when a mailbox has no primary group', function () {
    $mailbox = Mailbox::query()->create([
        'name' => 'Help Desk',
        'address' => 'help@contoso.com',
    ]);

    $reader = new FakeMailboxReader;
    $reader->messages = [inboundMail()];
    app()->instance(MailboxReader::class, $reader);

    $this->artisan('tickets:fetch-mail')
        ->expectsOutputToContain('no primary support group')
        ->assertFailed();

    $mailbox->update(['is_active' => false]);

    $this->artisan('tickets:fetch-mail')->assertSuccessful();
    expect($reader->marked)->toBe([]);
});
