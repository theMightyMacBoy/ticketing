<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    use HasVisibility;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * Get Tickets RelationShip
     */
    public function tickets(): BelongsToMany
    {
        $table = config('laravel_ticket.table_names.category_ticket');

        return $this->belongsToMany(
            config('laravel_ticket.models.ticket'),
            $table['table'],
            $table['columns']['category_foreign_id'],
            $table['columns']['ticket_foreign_id'],
        );
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeRoots(Builder $builder): Builder
    {
        return $builder->whereNull('parent_id');
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config(
            'laravel_ticket.table_names.categories',
            parent::getTable()
        );
    }
}
