<?php

namespace Coderflex\LaravelTicket\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTickets
{
    /**
     * Get User tickets relationship
     */
    public function tickets(): HasMany
    {
        $model = config(
            'laravel_ticket.models.ticket',
            parent::getTable()
        );

        return $this->hasMany($model, 'user_id');
    }

    /**
     * Get User tickets relationship
     */
    public function messages(): HasMany
    {
        $model = config(
            'laravel_ticket.models.message',
            parent::getTable()
        );

        return $this->hasMany($model, 'user_id');
    }

    /**
     * Tickets currently assigned to this user.
     */
    public function assignedTickets(): HasMany
    {
        $model = config('laravel_ticket.models.ticket');

        return $this->hasMany($model, 'assigned_to');
    }

    /**
     * Tickets this user is watching.
     */
    public function watchedTickets(): BelongsToMany
    {
        return $this->belongsToMany(
            config('laravel_ticket.models.ticket'),
            config('laravel_ticket.table_names.ticket_watchers', 'ticket_watchers')
        )->withTimestamps();
    }
}
