<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'minutes',
        'description',
        'logged_on',
    ];

    protected $casts = [
        'minutes' => 'integer',
        'logged_on' => 'date',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    protected function configuredTable(): string
    {
        return 'work_logs';
    }
}
