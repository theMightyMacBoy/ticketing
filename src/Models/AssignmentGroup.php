<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentGroup extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('laravel_ticket.table_names.assignment_group_user', 'assignment_group_user')
        )->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    public function addMember(Model|int $user): void
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        $this->members()->syncWithoutDetaching([$id]);
    }

    public function removeMember(Model|int $user): void
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        $this->members()->detach($id);
    }

    protected function configuredTable(): string
    {
        return 'assignment_groups';
    }
}
