<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'domain',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('laravel_ticket.table_names.organization_user', 'organization_user')
        )->withPivot('role')->withTimestamps();
    }

    protected function configuredTable(): string
    {
        return 'organizations';
    }
}
