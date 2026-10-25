<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Exceptions\MailboxMisconfigured;
use Coderflex\LaravelTicket\Mail\GraphMailboxReader;
use Coderflex\LaravelTicket\Mail\MailboxPurger;
use Coderflex\LaravelTicket\Mail\MailboxReader;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Tests\Support\FakeMailboxReader;
use Illuminate\Support\Facades\Http;

function retentionMailbox(?int $days, string $address = 'help@contoso.com'): Mailbox
{
    return Mailbox::query()->create([
        'name' => 'Help Desk',
        'address' => $address,
        'tenant_id' => 'tenant-1',
        'client_id' => 'client-1',
        'client_secret' => 'secret-1',
        'delete_read_after_days' => $days,
    ]);
}

it('leaves the inbox alone when a mailbox has no retention rule', function () {
    $mailbox = retentionMailbox(null);

    Http::fake();

    expect(app(MailboxPurger::class)->purge($mailbox))->toBe(0);

    Http::assertNothingSent();
});

it('deletes read inbox mail older than the mailbox rule at utc midnight', function () {
    config()->set('app.timezone', 'America/Detroit');
    Carbon::setTestNow(Carbon::parse('2026-10-25 00:30:00', 'UTC'));

    $mailbox = retentionMailbox(7);
    Mailbox::query()->create([
        'name' => 'Quiet',
        'address' => 'quiet@contoso.com',
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'login.microsoftonline.com')) {
            return Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ]);
        }

        if ($request->method() === 'DELETE') {
            return Http::response('', 204);
        }

        if (str_contains($request->url(), 'page=2')) {
            return Http::response([
                'value' => [[
                    'id' => 'old-2',
                    'isRead' => true,
                    'receivedDateTime' => '2026-10-02T00:00:00Z',
                ]],
            ]);
        }

        if (str_contains($request->url(), 'inbox/messages')) {
            return Http::response([
                'value' => [
                    [
                        'id' => 'old-1',
                        'isRead' => true,
                        'receivedDateTime' => '2026-10-01T00:00:00Z',
                    ],
                    [
                        'id' => 'unread-1',
                        'isRead' => false,
                        'receivedDateTime' => '2026-09-01T00:00:00Z',
                    ],
                    [
                        'id' => 'fresh-1',
                        'isRead' => true,
                        'receivedDateTime' => '2026-10-20T00:00:00Z',
                    ],
                ],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users/help%40contoso.com/mailFolders/inbox/messages?page=2',
            ]);
        }

        return Http::response('missing', 404);
    });

    expect(app(GraphMailboxReader::class)->deleteReadBefore(
        $mailbox,
        Carbon::parse('2026-10-18 00:00:00', 'UTC')
    ))->toBe(2);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), 'inbox/messages')
            && str_contains(urldecode($request->url()), 'isRead eq true')
            && str_contains(urldecode($request->url()), 'receivedDateTime lt 2026-10-18T00:00:00Z');
    });

    $deleted = collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->filter(fn ($request) => $request->method() === 'DELETE')
        ->map(fn ($request) => urldecode($request->url()))
        ->implode(' ');

    expect($deleted)->toContain('old-1')
        ->and($deleted)->toContain('old-2')
        ->and($deleted)->not->toContain('unread-1')
        ->and($deleted)->not->toContain('fresh-1');
});

it('purges only mailboxes with a retention rule and uses the utc date', function () {
    config()->set('app.timezone', 'America/Detroit');
    Carbon::setTestNow(Carbon::parse('2026-10-25 00:30:00', 'UTC'));

    retentionMailbox(7);
    retentionMailbox(null, 'quiet@contoso.com');

    $reader = new FakeMailboxReader;
    $reader->deleteCount = 4;
    app()->instance(MailboxReader::class, $reader);

    $this->artisan('tickets:purge-mail')
        ->expectsOutputToContain('deleted 4 read messages older than 7 days')
        ->assertSuccessful();

    expect($reader->deleteBefore?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-10-18T00:00:00Z');

    $this->artisan('tickets:purge-mail', ['--mailbox' => 'quiet@contoso.com'])
        ->expectsOutputToContain('retention is off')
        ->assertSuccessful();
});

it('reports a delete that exchange refused', function () {
    $mailbox = retentionMailbox(14);

    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response([
            'access_token' => 'token-1',
            'expires_in' => 3600,
        ]),
        'https://graph.microsoft.com/*/users/*/mailFolders/inbox/messages*' => Http::response([
            'value' => [[
                'id' => 'old-1',
                'isRead' => true,
                'receivedDateTime' => '2020-01-01T00:00:00Z',
            ]],
        ]),
        'https://graph.microsoft.com/*/users/*/messages/*' => Http::response([
            'error' => ['code' => 'ErrorAccessDenied'],
        ], 403),
    ]);

    expect(fn () => app(MailboxPurger::class)->purge($mailbox))
        ->toThrow(MailboxMisconfigured::class, 'Mail.ReadWrite');

    $this->artisan('tickets:purge-mail')->assertFailed();
});
