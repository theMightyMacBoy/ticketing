<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $assignment_group_id
 */
class Service extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'assignment_group_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assignmentGroup(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.assignment_group'));
    }

    public function configurationItems(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.configuration_item'));
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    protected function configuredTable(): string
    {
        return 'services';
    }
}
