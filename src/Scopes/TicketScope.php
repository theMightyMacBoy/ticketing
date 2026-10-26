<?php

namespace Coderflex\LaravelTicket\Scopes;

use Coderflex\LaravelTicket\Access\TicketVisibility;
use Coderflex\LaravelTicket\Enums\Priority;
use Coderflex\LaravelTicket\Enums\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait TicketScope
{
    /**
     * Get closed tickets
     */
    public function scopeClosed(Builder $builder): Builder
    {
        return $builder->where('status', Status::CLOSED->value);
    }

    /**
     * Get opened tickets
     */
    public function scopeOpened(Builder $builder): Builder
    {
        return $builder->where('status', Status::OPEN->value);
    }

    /**
     * Get resolved tickets
     */
    public function scopeResolved(Builder $builder): Builder
    {
        return $builder->where('is_resolved', true);
    }

    /**
     * Get unresolved tickets
     */
    public function scopeUnresolved(Builder $builder): Builder
    {
        return $builder->where('is_resolved', false);
    }

    /**
     * Get locked tickets
     */
    public function scopeLocked(Builder $builder): Builder
    {
        return $builder->where('is_locked', true);
    }

    /**
     * Get unlocked tickets
     */
    public function scopeUnlocked(Builder $builder): Builder
    {
        return $builder->where('is_locked', false);
    }

    /**
     * Get custom priority tickets
     */
    public function scopeWithPriority(Builder $builder, string $priority): Builder
    {
        return $builder->where('priority', $priority);
    }

    /**
     * Get low priority tickets
     */
    public function scopeWithLowPriority(Builder $builder): Builder
    {
        return $builder->where('priority', Priority::LOW->value);
    }

    /**
     * Get normal priority tickets
     */
    public function scopeWithNormalPriority(Builder $builder): Builder
    {
        return $builder->where('priority', Priority::NORMAL->value);
    }

    /**
     * Get high priority tickets
     */
    public function scopeWithHighPriority(Builder $builder): Builder
    {
        return $builder->where('priority', Priority::HIGH->value);
    }

    /**
     * Get archived tickets
     */
    public function scopeArchived(Builder $builder): Builder
    {
        return $builder->where('status', Status::ARCHIVED->value);
    }

    /**
     * Get unarchived tickets
     */
    public function scopeUnArchived(Builder $builder): Builder
    {
        return $builder->whereNot('status', Status::ARCHIVED->value);
    }

    /**
     * Tickets that still need an agent.
     */
    public function scopeActive(Builder $builder): Builder
    {
        return $builder->whereIn('status', Status::active());
    }

    public function scopeOfType(Builder $builder, string $type): Builder
    {
        return $builder->where('type', $type);
    }

    public function scopePending(Builder $builder): Builder
    {
        return $builder->where('status', Status::PENDING->value);
    }

    public function scopeBreached(Builder $builder): Builder
    {
        return $builder->where(function (Builder $query) {
            $query->where('response_breached', true)
                ->orWhere('resolution_breached', true);
        });
    }

    public function scopeWithMediumPriority(Builder $builder): Builder
    {
        return $builder->where('priority', Priority::MEDIUM->value);
    }

    public function scopeWithCriticalPriority(Builder $builder): Builder
    {
        return $builder->where('priority', Priority::CRITICAL->value);
    }

    public function scopeAssignedTo(Builder $builder, Model|int $user): Builder
    {
        $id = $user instanceof Model ? $user->getKey() : $user;

        return $builder->where('assigned_to', $id);
    }

    public function scopeInGroup(Builder $builder, Model|int $group): Builder
    {
        $id = $group instanceof Model ? $group->getKey() : $group;

        return $builder->where('assignment_group_id', $id);
    }

    /**
     * Tickets the user is allowed to see: own, affected, assigned, watched, or queued.
     * A platform admin still cannot see a confidential ticket they are not involved in.
     */
    public function scopeVisibleTo(Builder $builder, Model $user): Builder
    {
        if (TicketVisibility::manages($user)) {
            return $builder->where(function (Builder $query) use ($user) {
                $query->where($query->qualifyColumn('is_confidential'), false)
                    ->orWhere(function (Builder $inner) use ($user) {
                        TicketVisibility::apply($inner, $user);
                    });
            });
        }

        return $builder->where(function (Builder $query) use ($user) {
            TicketVisibility::apply($query, $user);
        });
    }

    public function scopeSearch(Builder $builder, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $builder;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $builder->where(function (Builder $query) use ($like) {
            $first = true;

            foreach (['ticket_number', 'title', 'requester_email', 'message'] as $column) {
                $sql = $query->qualifyColumn($column).' like ? escape ?';

                if ($first) {
                    $query->whereRaw($sql, [$like, '\\']);
                    $first = false;

                    continue;
                }

                $query->orWhereRaw($sql, [$like, '\\']);
            }
        });
    }
}
