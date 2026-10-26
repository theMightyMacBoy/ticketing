<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 */
class Department extends Model
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

    public function groups(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.assignment_group'));
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.mailbox'));
    }

    public function services(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.service'));
    }

    public function categories(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.category'));
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket_type'));
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.sla_policy'));
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    public function calendar(): HasOne
    {
        return $this->hasOne(config('laravel_ticket.models.business_calendar'));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('laravel_ticket.table_names.department_user', 'department_user')
        )->withPivot('is_agent')->withTimestamps();
    }

    public function agents(): BelongsToMany
    {
        return $this->users()->wherePivot('is_agent', true);
    }

    public function addAgent(Model|int $user): void
    {
        $this->grant($user, true);
    }

    public function addMember(Model|int $user): void
    {
        $this->grant($user, false);
    }

    public function defineType(string $name, string $prefix, bool $requiresApproval = false, ?string $slug = null): TicketTypeDefinition
    {
        $slug ??= Str::slug($name, '_');

        $type = TicketTypeDefinition::query()->firstOrNew([
            'department_id' => $this->getKey(),
            'slug' => $slug,
        ]);

        $type->fill([
            'name' => $name,
            'prefix' => strtoupper($prefix),
            'requires_approval' => $requiresApproval,
            'is_active' => true,
        ]);
        $type->save();

        return $type;
    }

    /**
     * @param  array<string, array{start: string, end: string}>  $week
     * @param  array<int, string>  $holidays
     */
    public function setCalendar(array $week, string $timezone = 'UTC', array $holidays = []): BusinessCalendar
    {
        $calendar = $this->calendar()->first();

        if (! $calendar instanceof BusinessCalendar) {
            $calendar = new BusinessCalendar([
                'department_id' => $this->getKey(),
            ]);
        }

        $calendar->fill([
            'name' => $this->name,
            'timezone' => $timezone,
            'week' => $week,
            'holidays' => $holidays,
        ]);
        $calendar->save();

        return $calendar;
    }

    /**
     * @return array<int, int>
     */
    public static function agentDepartmentIds(Model $user): array
    {
        return DB::table(config('laravel_ticket.table_names.department_user', 'department_user'))
            ->where('user_id', $user->getKey())
            ->where('is_agent', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function configuredTable(): string
    {
        return 'departments';
    }

    private function grant(Model|int $user, bool $agent): void
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        $this->users()->syncWithoutDetaching([$id]);
        $this->users()->updateExistingPivot($id, ['is_agent' => $agent]);
    }
}
