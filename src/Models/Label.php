<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
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
        $table = config('laravel_ticket.table_names.label_ticket');

        return $this->belongsToMany(
            config('laravel_ticket.models.ticket'),
            $table['table'],
            $table['columns']['label_foreign_id'],
            $table['columns']['ticket_foreign_id'],
        );
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config(
            'laravel_ticket.table_names.labels',
            parent::getTable()
        );
    }
}
