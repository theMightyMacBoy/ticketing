<?php

namespace Coderflex\LaravelTicket\Mail;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class InboundEmail
{
    public function __construct(
        public string $graphId,
        public ?string $internetMessageId,
        public ?string $inReplyTo,
        public string $references,
        public string $fromAddress,
        public ?string $fromName,
        public string $subject,
        public string $body,
        public CarbonInterface $receivedAt,
    ) {}

    /**
     * Message-ids this email claims to continue, without angle brackets.
     *
     * @return array<int, string>
     */
    public function referenceIds(): array
    {
        $ids = [];

        foreach ([$this->inReplyTo, $this->references] as $header) {
            if ($header === null || trim($header) === '') {
                continue;
            }

            preg_match_all('/<([^>]+)>/', $header, $wrapped);

            $split = preg_split('/\s+/', trim($header));
            $found = $wrapped[1] !== [] ? $wrapped[1] : ($split === false ? [] : $split);

            foreach ($found as $id) {
                $normalized = self::normalizeId($id);

                if ($normalized !== null) {
                    $ids[] = $normalized;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function normalizeId(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, '<') && str_ends_with($id, '>')) {
            $id = substr($id, 1, -1);
        }

        return strtolower(trim($id));
    }

    public static function receivedAt(string $value): Carbon
    {
        return Carbon::parse($value);
    }
}
