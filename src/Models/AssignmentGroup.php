<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $mailbox_id
 * @property int|null $department_id
 * @property bool $is_mailbox_primary
 */
class AssignmentGroup extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'mailbox_id',
        'department_id',
        'is_mailbox_primary',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_mailbox_primary' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.mailbox'));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    /**
     * Attach this group to a shared mailbox.
     *
     * The first group on a mailbox is the primary. New mail opens there.
     * Pass $primary to move that role to this group.
     */
    public function connectMailbox(Mailbox $mailbox, bool $primary = false): void
    {
        $anotherGroup = static::query()
            ->where('mailbox_id', $mailbox->getKey())
            ->whereKeyNot($this->getKey())
            ->exists();

        $makePrimary = $primary || ! $anotherGroup;

        if ($makePrimary) {
            static::query()
                ->where('mailbox_id', $mailbox->getKey())
                ->whereKeyNot($this->getKey())
                ->update(['is_mailbox_primary' => false]);
        }

        $this->forceFill([
            'mailbox_id' => $mailbox->getKey(),
            'is_mailbox_primary' => $makePrimary,
        ])->save();
    }

    public function makePrimary(): void
    {
        if (! $this->mailbox_id) {
            return;
        }

        static::query()
            ->where('mailbox_id', $this->mailbox_id)
            ->whereKeyNot($this->getKey())
            ->update(['is_mailbox_primary' => false]);

        $this->forceFill(['is_mailbox_primary' => true])->save();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('laravel_ticket.table_names.assignment_group_user', 'assignment_group_user')
        )->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    public function addMember(Model|int $user): void
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        $this->members()->syncWithoutDetaching([$id]);
    }

    public function removeMember(Model|int $user): void
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        $this->members()->detach($id);
    }

    protected function configuredTable(): string
    {
        return 'assignment_groups';
    }
}
