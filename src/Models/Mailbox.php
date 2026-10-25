<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Coderflex\LaravelTicket\Exceptions\MailboxMisconfigured;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $name
 * @property string $address
 * @property string|null $tenant_id
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property bool $is_active
 * @property int|null $delete_read_after_days
 * @property Carbon|null $last_synced_at
 */
class Mailbox extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'address',
        'tenant_id',
        'client_id',
        'client_secret',
        'is_active',
        'delete_read_after_days',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'is_active' => 'boolean',
        'delete_read_after_days' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (Mailbox $mailbox) {
            if ($mailbox->wasChanged(['client_secret', 'client_id', 'tenant_id'])) {
                Cache::forget($mailbox->tokenCacheKey());
            }
        });
    }

    public function groups(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.assignment_group'));
    }

    public function primaryGroup(): ?AssignmentGroup
    {
        $group = $this->groups()->where('is_mailbox_primary', true)->first();

        return $group instanceof AssignmentGroup ? $group : null;
    }

    public function tenantId(): string
    {
        return $this->requiredCredential(
            $this->tenant_id ?: config('laravel_ticket.mail.graph.tenant_id'),
            'tenant id'
        );
    }

    public function clientId(): string
    {
        return $this->requiredCredential(
            $this->client_id ?: config('laravel_ticket.mail.graph.client_id'),
            'client id'
        );
    }

    public function clientSecret(): string
    {
        return $this->requiredCredential(
            $this->client_secret ?: config('laravel_ticket.mail.graph.client_secret'),
            'client secret'
        );
    }

    public function tokenCacheKey(): string
    {
        return 'laravel-ticket.graph-token.'.$this->getKey();
    }

    public function domain(): string
    {
        $parts = explode('@', $this->address);

        return $parts[1] ?? 'localhost';
    }

    protected function configuredTable(): string
    {
        return 'mailboxes';
    }

    private function requiredCredential(mixed $value, string $label): string
    {
        if (! is_string($value) || $value === '') {
            throw new MailboxMisconfigured("Mailbox [{$this->address}] is missing a Graph {$label}.");
        }

        return $value;
    }
}
