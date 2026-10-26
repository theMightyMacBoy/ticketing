<?php

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Mail\TicketReplyMail;
use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\RichText\HtmlBody;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function richPhoto(): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent('rack.png', is_string($png) ? $png : '');
}

function richActors(): array
{
    $middleware = array_values(array_filter([
        class_exists(ValidateCsrfToken::class) ? ValidateCsrfToken::class : null,
        class_exists(VerifyCsrfToken::class) ? VerifyCsrfToken::class : null,
    ]));

    test()->withoutMiddleware($middleware);
    config()->set('laravel_ticket.agents.callback', fn ($user) => $user->email === 'agent@example.com');

    $requester = User::factory()->create(['email' => 'ada@example.com']);
    $agent = User::factory()->create(['email' => 'agent@example.com']);
    $ticket = app(TicketDesk::class)->open([
        'title' => 'Badge photo',
        'message' => 'The reader is dark',
        'user_id' => $requester->id,
        'requester_email' => 'ada@example.com',
    ], $requester);

    return [$requester, $agent, $ticket];
}

it('keeps safe formatting and drops scripts and remote images', function () {
    $html = app(HtmlBody::class)->clean(
        '<p>Hello <strong>there</strong><script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src="https://evil.test/a.png" alt="track"><a href="https://example.com/ok">Docs</a>',
        1
    );

    expect($html)->toContain('<strong>there</strong>')
        ->and($html)->toContain('href="https://example.com/ok"')
        ->and($html)->toContain('rel="noopener noreferrer"')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('evil.test')
        ->and(app(HtmlBody::class)->asPlain('<p>Hello</p>'))->toBe('Hello')
        ->and(app(HtmlBody::class)->asPlain('Hello'))->toBe('Hello')
        ->and(app(HtmlBody::class)->asPlain('<p><strong>Hello</strong></p>'))->toBeNull();
});

it('embeds a photo in an internal note and hides it from the requester', function () {
    Storage::fake('public');
    [$requester, $agent, $ticket] = richActors();

    $upload = $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.attachments.store', $ticket), [
            'file' => richPhoto(),
        ])
        ->assertOk()
        ->assertJsonStructure(['id', 'url', 'name']);

    $url = (string) $upload->json('url');
    $id = (int) $upload->json('id');

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.note', $ticket), [
            'message' => '<p>See the <strong>badge</strong> closet</p><img src="'.$url.'" alt="rack.png">',
        ])
        ->assertRedirect();

    $note = Message::query()->where('visibility', 'internal')->sole();

    expect($note->format)->toBe('html')
        ->and($note->message)->toContain('<strong>badge</strong>')
        ->and(Attachment::query()->find($id)->message_id)->toBe($note->id)
        ->and(Attachment::query()->find($id)->is_inline)->toBeTrue();

    $this->actingAs($agent)
        ->get(route('laravel-ticket.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('<strong>badge</strong>', false)
        ->assertSee($url, false);

    $this->actingAs($requester)
        ->get(route('laravel-ticket.portal.tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('badge closet')
        ->assertDontSee($url, false);

    $this->actingAs($requester)
        ->get(route('laravel-ticket.tickets.attachments.show', [$ticket, $id]))
        ->assertForbidden();

    $this->actingAs($agent)
        ->get(route('laravel-ticket.tickets.attachments.show', [$ticket, $id]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('x-content-type-options', 'nosniff');

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.reply', $ticket), [
            'message' => '<p>Public copy</p><img src="'.$url.'" alt="rack.png">',
        ])
        ->assertRedirect();

    $reply = Message::query()->where('visibility', 'public')->where('user_id', $agent->id)->sole();

    expect($reply->format)->toBe('plain')
        ->and($reply->message)->toBe('Public copy')
        ->and($reply->message)->not->toContain('<img');
});

it('lets the requester download a file on a public reply', function () {
    Storage::fake('public');
    [$requester, $agent, $ticket] = richActors();

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.reply', $ticket), [
            'message' => '<p>The form is attached.</p>',
            'files' => [
                UploadedFile::fake()->createWithContent('west-door.txt', "serial 14\n"),
            ],
        ])
        ->assertRedirect();

    $reply = Message::query()->where('message', 'The form is attached.')->sole();
    $file = Attachment::query()->where('message_id', $reply->id)->sole();

    expect($reply->format)->toBe('plain')
        ->and($file->is_inline)->toBeFalse()
        ->and($file->original_name)->toBe('west-door.txt');

    $this->actingAs($requester)
        ->get(route('laravel-ticket.portal.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('The form is attached.')
        ->assertSee('west-door.txt');

    $this->actingAs($requester)
        ->get(route('laravel-ticket.tickets.attachments.show', [$ticket, $file]))
        ->assertOk()
        ->assertSee('serial 14')
        ->assertHeader('x-content-type-options', 'nosniff');
});

it('emails a formatted reply with the photo embedded', function () {
    Storage::fake('public');
    Mail::fake();
    [$requester, $agent, $ticket] = richActors();

    $mailbox = Mailbox::query()->create([
        'name' => 'Help Desk',
        'address' => 'help@example.com',
        'tenant_id' => 'tenant-1',
        'client_id' => 'client-1',
        'client_secret' => 'secret-1',
        'is_active' => false,
    ]);
    $ticket->update(['mailbox_id' => $mailbox->id]);

    $upload = $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.attachments.store', $ticket), [
            'file' => richPhoto(),
        ])
        ->assertOk();

    $url = (string) $upload->json('url');

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.reply', $ticket), [
            'message' => '<p><strong>Done</strong></p><img src="'.$url.'" alt="rack.png">',
            'files' => [
                UploadedFile::fake()->createWithContent('west-door.txt', "serial 14\n"),
            ],
        ])
        ->assertRedirect();

    expect($ticket->fresh()->first_response_at)->not->toBeNull();

    Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) use ($requester) {
        return $mail->hasTo('ada@example.com')
            && is_string($mail->bodyHtml)
            && str_contains($mail->bodyHtml, '<strong>Done</strong>')
            && str_contains($mail->bodyHtml, 'cid:att')
            && str_contains($mail->bodyHtml, '@ticket')
            && ! str_contains($mail->bodyText, '<strong')
            && count($mail->inline) === 1
            && str_contains($mail->inline[0]['cid'], '@')
            && $mail->inline[0]['bytes'] !== ''
            && count($mail->files) === 1
            && $mail->files[0]['name'] === 'west-door.txt'
            && $requester->email === 'ada@example.com';
    });

    Mail::assertSent(TicketReplyMail::class, 1);
});

it('refuses an empty reply and deletes files when the requester is erased', function () {
    Storage::fake('public');
    [$requester, $agent, $ticket] = richActors();

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.reply', $ticket), ['message' => '   '])
        ->assertSessionHasErrors(['message' => 'Write a reply, or attach a file, before sending.']);

    $this->actingAs($agent)
        ->post(route('laravel-ticket.tickets.reply', $ticket), [
            'message' => '',
            'files' => [
                UploadedFile::fake()->createWithContent('west-door.txt', "serial 14\n"),
            ],
        ])
        ->assertRedirect();

    $file = Attachment::query()->sole();
    $path = $file->path;
    Storage::disk('public')->assertExists($path);

    $ticket->note('<p><strong>Keep</strong></p>', $agent, 'html');

    expect(app(TicketDesk::class)->eraseRequester('ada@example.com', $requester))->toBe(1)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Message::query()->where('ticket_id', $ticket->id)->where('format', 'html')->exists())->toBeFalse()
        ->and(Message::query()->where('ticket_id', $ticket->id)->pluck('message')->unique()->values()->all())->toBe(['[erased]']);

    Storage::disk('public')->assertMissing($path);
});
