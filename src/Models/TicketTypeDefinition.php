<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $department_id
 * @property string $owner_key
 * @property string $name
 * @property string $slug
 * @property string $prefix
 * @property bool $requires_approval
 * @property bool $is_active
 */
class TicketTypeDefinition extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'prefix',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (TicketTypeDefinition $type) {
            $type->owner_key = $type->department_id ? (string) $type->department_id : 'global';
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    public static function resolvePrefix(string $slug, ?int $departmentId): ?string
    {
        $match = null;

        if ($departmentId) {
            $specific = static::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->where('department_id', $departmentId)
                ->first();

            $match = $specific instanceof self ? $specific : null;
        }

        if (! $match instanceof self) {
            $global = static::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->whereNull('department_id')
                ->first();

            $match = $global instanceof self ? $global : null;
        }

        if (! $match instanceof self || $match->prefix === '') {
            return null;
        }

        return strtoupper($match->prefix);
    }

    protected function configuredTable(): string
    {
        return 'ticket_types';
    }
}
