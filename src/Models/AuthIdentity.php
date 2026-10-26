<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $email
 * @property string $method
 * @property int|null $identity_provider_id
 * @property string|null $subject
 */
class AuthIdentity extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'email',
        'method',
        'identity_provider_id',
        'subject',
    ];

    protected static function booted(): void
    {
        static::saving(function (AuthIdentity $identity) {
            $identity->email = strtolower(trim($identity->email));
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.identity_provider'), 'identity_provider_id');
    }

    protected function configuredTable(): string
    {
        return 'auth_identities';
    }
}
