<?php

namespace Coderflex\LaravelTicket\Access;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamRoster
{
    /**
     * Email addresses of people whose synced manager is this account.
     *
     * @return list<string>
     */
    public static function reportEmails(Model $manager): array
    {
        $email = strtolower(trim((string) $manager->getAttribute('email')));

        if ($email === '') {
            return [];
        }

        $emails = AuthIdentity::query()
            ->where('manager_email', $email)
            ->orderBy('email')
            ->pluck('email')
            ->map(fn ($value) => strtolower((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        return array_values($emails);
    }

    public static function supervises(Model $manager, Ticket $ticket): bool
    {
        if (self::hiddenFromManagers($ticket)) {
            return false;
        }

        $reports = self::reportEmails($manager);

        if ($reports === []) {
            return false;
        }

        $requester = strtolower(trim((string) $ticket->requester_email));

        if ($requester !== '' && in_array($requester, $reports, true)) {
            return true;
        }

        $owner = $ticket->user()->first();

        if (! $owner instanceof Model) {
            return false;
        }

        $ownerEmail = strtolower(trim((string) $owner->getAttribute('email')));

        return $ownerEmail !== '' && in_array($ownerEmail, $reports, true);
    }

    /**
     * Tickets filed by direct reports. Open means not resolved, closed, cancelled, or archived.
     *
     * @return Builder<Ticket>
     */
    public static function tickets(Model $manager, bool $onlyOpen): Builder
    {
        $emails = self::reportEmails($manager);
        $query = Ticket::query();

        if ($emails === []) {
            return $query->whereRaw('0 = 1');
        }

        $marks = implode(', ', array_fill(0, count($emails), '?'));

        $query->where(function (Builder $inner) use ($emails, $marks) {
            $inner->whereRaw('lower('.$inner->qualifyColumn('requester_email').') in ('.$marks.')', $emails)
                ->orWhereHas('user', function (Builder $users) use ($emails, $marks) {
                    $users->whereRaw('lower('.$users->qualifyColumn('email').') in ('.$marks.')', $emails);
                });
        });

        if ($onlyOpen) {
            $query->whereNotIn($query->qualifyColumn('status'), Status::terminal());
        }

        return $query->whereDoesntHave('assignmentGroup', function (Builder $groups) {
            $groups->where($groups->qualifyColumn('hide_from_managers'), true);
        });
    }

    /**
     * A support group can keep its tickets off the requester's manager.
     */
    public static function hiddenFromManagers(Ticket $ticket): bool
    {
        if (! $ticket->assignment_group_id) {
            return false;
        }

        $group = $ticket->assignmentGroup()->first();

        return $group instanceof AssignmentGroup && $group->hide_from_managers;
    }
}
