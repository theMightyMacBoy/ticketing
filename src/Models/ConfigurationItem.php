<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConfigurationItem extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'service_id',
        'name',
        'slug',
        'type',
        'status',
        'description',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.service'));
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(
            config('laravel_ticket.models.ticket'),
            config('laravel_ticket.table_names.configuration_item_ticket', 'configuration_item_ticket')
        )->withTimestamps();
    }

    protected function configuredTable(): string
    {
        return 'configuration_items';
    }
}
