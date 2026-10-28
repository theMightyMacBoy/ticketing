<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property string $name
 * @property string $slug
 * @property array<int, string> $domains
 * @property string $issuer
 * @property string $client_id
 * @property string $client_secret
 * @property bool $is_active
 * @property bool $sync_manager
 */
class IdentityProvider extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'domains',
        'issuer',
        'client_id',
        'client_secret',
        'is_active',
        'sync_manager',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'domains' => 'array',
        'client_secret' => 'encrypted',
        'is_active' => 'boolean',
        'sync_manager' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (IdentityProvider $provider) {
            if ($provider->slug === '') {
                $provider->slug = Str::slug($provider->name, '-');
            }

            $domains = [];

            foreach ($provider->domains ?? [] as $domain) {
                $normalized = strtolower(ltrim(trim((string) $domain), '@'));

                if ($normalized !== '') {
                    $domains[] = $normalized;
                }
            }

            $provider->domains = array_values(array_unique($domains));

            foreach ($provider->domains as $domain) {
                $taken = static::query()
                    ->when($provider->exists, fn ($query) => $query->whereKeyNot($provider->getKey()))
                    ->whereJsonContains('domains', $domain)
                    ->exists();

                if ($taken) {
                    throw new InvalidArgumentException("{$domain} is already assigned to a sign-in provider.");
                }
            }
        });
    }

    public function identities(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.auth_identity'));
    }

    public static function forEmail(string $email): ?self
    {
        $at = strrchr(strtolower(trim($email)), '@');

        if ($at === false || strlen($at) < 2) {
            return null;
        }

        $match = static::query()
            ->where('is_active', true)
            ->whereJsonContains('domains', substr($at, 1))
            ->first();

        return $match instanceof self ? $match : null;
    }

    protected function configuredTable(): string
    {
        return 'identity_providers';
    }
}
