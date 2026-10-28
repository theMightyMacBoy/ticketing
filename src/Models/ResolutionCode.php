<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\HasVisibility;
use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResolutionCode extends Model
{
    use HasVisibility;
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    protected function configuredTable(): string
    {
        return 'resolution_codes';
    }
}
