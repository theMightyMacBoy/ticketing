<?php

namespace Coderflex\LaravelTicket\Mail;

use Coderflex\LaravelTicket\Exceptions\MailboxMisconfigured;
use Coderflex\LaravelTicket\Models\Mailbox;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GraphMailboxReader implements MailboxReader
{
    /**
     * @return array<int, InboundEmail>
     */
    public function fetch(Mailbox $mailbox): array
    {
        $response = $this->graph($mailbox)->withHeaders([
            'Prefer' => 'outlook.body-content-type="text"',
        ])->get($this->inboxUrl($mailbox), [
            '$top' => (int) config('laravel_ticket.mail.fetch_limit', 25),
            '$select' => 'id,subject,body,from,receivedDateTime,internetMessageId,internetMessageHeaders,isRead',
            '$filter' => 'isRead eq false',
        ]);

        $this->guard($response, "Graph could not read the inbox for [{$mailbox->address}]");

        $items = $response->json('value');

        if (! is_array($items)) {
            return [];
        }

        $messages = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $messages[] = $this->toEmail($item);
        }

        usort($messages, fn (InboundEmail $left, InboundEmail $right) => $left->receivedAt <=> $right->receivedAt);

        return $messages;
    }

    public function markProcessed(Mailbox $mailbox, string $graphId): void
    {
        $url = $this->graphBase().'/users/'.rawurlencode($mailbox->address).'/messages/'.rawurlencode($graphId);

        $response = $this->graph($mailbox)->patch($url, ['isRead' => true]);

        $this->guard($response, "Graph could not mark a message read for [{$mailbox->address}]");
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toEmail(array $item): InboundEmail
    {
        $from = $item['from']['emailAddress'] ?? [];
        $body = $item['body']['content'] ?? '';
        $contentType = strtolower((string) ($item['body']['contentType'] ?? 'text'));

        if (! is_string($body)) {
            $body = '';
        }

        if ($contentType === 'html') {
            $body = html_entity_decode(strip_tags($body));
        }

        $address = is_array($from) ? ($from['address'] ?? null) : null;
        $name = is_array($from) ? ($from['name'] ?? null) : null;

        return new InboundEmail(
            graphId: (string) $item['id'],
            internetMessageId: $this->stringOrNull($item['internetMessageId'] ?? null),
            inReplyTo: $this->header($item, 'In-Reply-To'),
            references: (string) ($this->header($item, 'References') ?? ''),
            fromAddress: is_string($address) ? $address : '',
            fromName: is_string($name) && $name !== '' ? $name : null,
            subject: is_string($item['subject'] ?? null) ? $item['subject'] : '',
            body: trim($body),
            receivedAt: InboundEmail::receivedAt((string) ($item['receivedDateTime'] ?? 'now')),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function header(array $item, string $name): ?string
    {
        $headers = $item['internetMessageHeaders'] ?? [];

        if (! is_array($headers)) {
            return null;
        }

        foreach ($headers as $header) {
            if (! is_array($header)) {
                continue;
            }

            if (strcasecmp((string) ($header['name'] ?? ''), $name) !== 0) {
                continue;
            }

            return $this->stringOrNull($header['value'] ?? null);
        }

        return null;
    }

    private function graph(Mailbox $mailbox): PendingRequest
    {
        return Http::withToken($this->token($mailbox))
            ->acceptJson()
            ->timeout(20);
    }

    private function token(Mailbox $mailbox): string
    {
        $cached = Cache::get($mailbox->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(20)->post(
            sprintf((string) config('laravel_ticket.mail.token_url'), rawurlencode($mailbox->tenantId())),
            [
                'client_id' => $mailbox->clientId(),
                'client_secret' => $mailbox->clientSecret(),
                'scope' => (string) config('laravel_ticket.mail.graph.scope', 'https://graph.microsoft.com/.default'),
                'grant_type' => 'client_credentials',
            ]
        );

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new MailboxMisconfigured(
                "Graph token request failed for [{$mailbox->address}] ({$this->errorCode($response)})."
            );
        }

        $expires = (int) $response->json('expires_in', 3600);
        $ttl = $expires > 90 ? $expires - 60 : max(30, $expires);

        Cache::put($mailbox->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    private function guard(Response $response, string $action): void
    {
        if ($response->failed()) {
            throw new MailboxMisconfigured("{$action} ({$this->errorCode($response)}).");
        }
    }

    private function errorCode(Response $response): string
    {
        $error = $response->json('error');

        if (is_array($error) && is_string($error['code'] ?? null) && $error['code'] !== '') {
            return $error['code'];
        }

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'HTTP '.$response->status();
    }

    private function inboxUrl(Mailbox $mailbox): string
    {
        return $this->graphBase().'/users/'.rawurlencode($mailbox->address).'/mailFolders/inbox/messages';
    }

    private function graphBase(): string
    {
        return rtrim((string) config('laravel_ticket.mail.graph_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
