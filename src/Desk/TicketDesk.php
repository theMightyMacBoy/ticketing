<?php

namespace Coderflex\LaravelTicket\Desk;

use Coderflex\LaravelTicket\Catalog\CatalogForm;
use Coderflex\LaravelTicket\Enums\Channel;
use Coderflex\LaravelTicket\Enums\Impact;
use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Enums\Urgency;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Sla\PriorityMatrix;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TicketDesk
{
    public function __construct(private PriorityMatrix $matrix) {}

    /**
     * Open a ticket and score its priority from impact and urgency.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(array $attributes, ?Model $requester = null): Ticket
    {
        $requester ??= auth()->user();
        $impact = (string) ($attributes['impact'] ?? Impact::LOW->value);
        $urgency = (string) ($attributes['urgency'] ?? Urgency::LOW->value);
        $attributes = $this->inheritRouting($attributes);
        $serviceId = isset($attributes['service_id']) ? (int) $attributes['service_id'] : null;
        CatalogForm::assertSatisfied($serviceId ?: null, $attributes);

        $ticket = Ticket::query()->create([
            'user_id' => $attributes['user_id'] ?? $requester?->getKey(),
            'affected_user_id' => $attributes['affected_user_id'] ?? null,
            'organization_id' => $attributes['organization_id'] ?? null,
            'assignment_group_id' => $attributes['assignment_group_id'] ?? null,
            'service_id' => $attributes['service_id'] ?? null,
            'title' => $attributes['title'],
            'message' => $attributes['message'] ?? null,
            'type' => $attributes['type'] ?? TicketType::INCIDENT->value,
            'impact' => $impact,
            'urgency' => $urgency,
            'priority' => $attributes['priority'] ?? $this->matrix->calculate($impact, $urgency),
            'channel' => $attributes['channel'] ?? Channel::PORTAL->value,
            'is_major' => (bool) ($attributes['is_major'] ?? false),
            'mailbox_id' => $attributes['mailbox_id'] ?? null,
            'requester_email' => $attributes['requester_email'] ?? null,
            'department_id' => $attributes['department_id'] ?? null,
            'is_confidential' => (bool) ($attributes['is_confidential'] ?? false),
            'custom_attributes' => $attributes['custom_attributes'] ?? null,
        ]);

        if (! empty($attributes['categories'])) {
            $ticket->attachCategories($attributes['categories']);
        }

        if (! empty($attributes['labels'])) {
            $ticket->attachLabels($attributes['labels']);
        }

        return $ticket;
    }

    /**
     * @return array{email: string, tickets: array<int, array<string, mixed>>}
     */
    public function exportForRequester(string $email, Model $actor): array
    {
        $tickets = $this->requesterTickets($email, $actor)->with('messages')->get();
        $rows = [];

        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }

            $includeInternal = $actor instanceof Authenticatable
                && Gate::forUser($actor)->allows('addInternalNote', $ticket);
            $messages = [];

            foreach ($ticket->messages as $message) {
                if (! $message instanceof Message) {
                    continue;
                }

                if (! $includeInternal && $message->visibility !== MessageVisibility::PUBLIC->value) {
                    continue;
                }

                $messages[] = [
                    'message' => $message->message,
                    'visibility' => $message->visibility,
                    'sender_email' => $message->sender_email,
                ];
            }

            $rows[] = [
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'message' => $ticket->message,
                'status' => $ticket->status,
                'requester_email' => $ticket->requester_email,
                'messages' => $messages,
            ];
        }

        return [
            'email' => strtolower(trim($email)),
            'tickets' => $rows,
        ];
    }

    public function eraseRequester(string $email, Model $actor): int
    {
        $ids = $this->requesterTickets($email, $actor)->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        $messages = config('laravel_ticket.table_names.messages');
        $table = is_array($messages) ? (string) ($messages['table'] ?? 'messages') : 'messages';
        $foreign = is_array($messages) ? (string) ($messages['columns']['ticket_foreign_id'] ?? 'ticket_id') : 'ticket_id';

        foreach (Attachment::query()->whereIn('ticket_id', $ids)->get() as $file) {
            if ($file->path !== '') {
                Storage::disk($file->disk)->delete($file->path);
            }
        }

        Attachment::query()->whereIn('ticket_id', $ids)->delete();

        DB::table($table)->whereIn($foreign, $ids)->update([
            'message' => '[erased]',
            'format' => 'plain',
            'sender_email' => null,
            'sender_name' => null,
            'updated_at' => now(),
        ]);

        Ticket::query()->whereIn('id', $ids)->update([
            'title' => 'Erased request',
            'message' => null,
            'requester_email' => null,
            'custom_attributes' => null,
        ]);

        return count($ids);
    }

    private function requesterTickets(string $email, Model $actor): Builder
    {
        $email = strtolower(trim($email));
        $departmentIds = Department::agentDepartmentIds($actor);
        $self = strcasecmp((string) $actor->getAttribute('email'), $email) === 0;

        return Ticket::query()
            ->where(function (Builder $query) use ($email) {
                $query->whereRaw('lower(requester_email) = ?', [$email])
                    ->orWhereHas('user', function (Builder $users) use ($email) {
                        $users->whereRaw('lower(email) = ?', [$email]);
                    });
            })
            ->where(function (Builder $query) use ($departmentIds, $self) {
                $query->where($query->qualifyColumn('is_confidential'), false);

                if ($departmentIds !== []) {
                    $query->orWhereIn($query->qualifyColumn('department_id'), $departmentIds);
                }

                if ($self) {
                    $query->orWhere($query->qualifyColumn('is_confidential'), true);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function inheritRouting(array $attributes): array
    {
        $service = null;

        if (! empty($attributes['service_id'])) {
            $found = Service::query()->find($attributes['service_id']);
            $service = $found instanceof Service ? $found : null;
        }

        if ($service instanceof Service) {
            $attributes['assignment_group_id'] ??= $service->assignment_group_id;
            $attributes['department_id'] ??= $service->department_id;
        }

        if (empty($attributes['department_id']) && ! empty($attributes['assignment_group_id'])) {
            $group = AssignmentGroup::query()->find($attributes['assignment_group_id']);

            if ($group instanceof AssignmentGroup && $group->department_id) {
                $attributes['department_id'] = $group->department_id;
            }
        }

        return $attributes;
    }
}
