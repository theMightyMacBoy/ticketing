<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $user_id
 * @property int|null $department_id
 * @property string $name
 * @property string $email
 * @property string|null $employee_number
 * @property bool $is_agent
 */
class StaffProfile extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'user_id',
        'department_id',
        'name',
        'email',
        'employee_number',
        'is_agent',
    ];

    protected $casts = [
        'is_agent' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    protected function configuredTable(): string
    {
        return 'staff_profiles';
    }
}
