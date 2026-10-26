<?php

namespace Coderflex\LaravelTicket\Concerns;

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Enums\LinkType;
use Coderflex\LaravelTicket\Enums\PendingReason;
use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Events\ApprovalRequested;
use Coderflex\LaravelTicket\Models\Approval;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\ConfigurationItem;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Organization;
use Coderflex\LaravelTicket\Models\ResolutionCode;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Models\TicketLink;
use Coderflex\LaravelTicket\Models\WorkLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

trait InteractsWithItsm
{
    public function start(): self
    {
        return $this->transitionTo(Status::IN_PROGRESS);
    }

    public function pend(string $reason): self
    {
        if (PendingReason::tryFrom($reason) === null) {
            throw new InvalidArgumentException("Unknown pending reason [{$reason}].");
        }

        $this->update([
            'status' => Status::PENDING->value,
            'pending_reason' => $reason,
        ]);

        return $this;
    }

    public function resolve(?ResolutionCode $code = null): self
    {
        $this->update([
            'status' => Status::RESOLVED->value,
            'is_resolved' => true,
            'resolution_code_id' => $code?->getKey() ?? $this->resolution_code_id,
        ]);

        return $this;
    }

    public function cancel(): self
    {
        return $this->transitionTo(Status::CANCELLED);
    }

    public function transitionTo(Status|string $status): self
    {
        $this->update([
            'status' => $status instanceof Status ? $status->value : $status,
        ]);

        return $this;
    }

    public function assignGroup(AssignmentGroup|int $group): self
    {
        $model = $group instanceof AssignmentGroup
            ? $group
            : AssignmentGroup::query()->find($group);

        $attributes = [
            'assignment_group_id' => $model instanceof AssignmentGroup ? $model->getKey() : $group,
        ];

        if ($model instanceof AssignmentGroup && $model->department_id) {
            $attributes['department_id'] = $model->department_id;
        }

        $this->update($attributes);

        return $this;
    }

    public function transferTo(AssignmentGroup|Department $target): self
    {
        if ($target instanceof Department) {
            $this->update([
                'department_id' => $target->getKey(),
                'assignment_group_id' => null,
            ]);

            return $this;
        }

        return $this->assignGroup($target);
    }

    /**
     * Open a child ticket for another department and link it to this case.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function spawnTask(array $attributes, AssignmentGroup|Department|null $target = null): Ticket
    {
        $attributes['user_id'] ??= $this->user_id;

        if ($target instanceof AssignmentGroup) {
            $attributes['assignment_group_id'] = $target->getKey();
            $attributes['department_id'] = $target->department_id;
        } elseif ($target instanceof Department) {
            $attributes['department_id'] = $target->getKey();
        }

        $child = app(TicketDesk::class)->open($attributes);
        $this->addChild($child);

        return $child;
    }

    public function associateOrganization(Organization|int $organization): self
    {
        $this->update([
            'organization_id' => $organization instanceof Organization ? $organization->getKey() : $organization,
        ]);

        return $this;
    }

    public function affect(Model|int $user): self
    {
        $this->update([
            'affected_user_id' => $user instanceof Model ? $user->getKey() : $user,
        ]);

        return $this;
    }

    public function assignService(Service|int $service): self
    {
        $service = $service instanceof Service ? $service : Service::query()->findOrFail($service);
        $attributes = ['service_id' => $service->getKey()];

        if (! $this->assignment_group_id && $service->assignment_group_id) {
            $attributes['assignment_group_id'] = $service->assignment_group_id;
        }

        $this->update($attributes);

        return $this;
    }

    public function watch(Model|int $user): void
    {
        $this->watchers()->syncWithoutDetaching([
            $user instanceof Model ? $user->getKey() : $user,
        ]);
    }

    public function unwatch(Model|int $user): void
    {
        $this->watchers()->detach($user instanceof Model ? $user->getKey() : $user);
    }

    public function logWork(int $minutes, ?string $description = null, Model|int|null $user = null): WorkLog
    {
        $userId = $user instanceof Model ? $user->getKey() : ($user ?? auth()->id());

        $log = new WorkLog([
            'ticket_id' => $this->getKey(),
            'user_id' => $userId,
            'minutes' => $minutes,
            'description' => $description,
            'logged_on' => now()->toDateString(),
        ]);
        $log->save();

        return $log;
    }

    public function requestApproval(Model $approver, ?Model $requester = null): Approval
    {
        $approval = new Approval([
            'ticket_id' => $this->getKey(),
            'approver_id' => $approver->getKey(),
            'requested_by' => $requester?->getKey() ?? auth()->id(),
            'status' => ApprovalStatus::PENDING->value,
        ]);
        $approval->save();

        event(new ApprovalRequested($approval));

        return $approval;
    }

    /**
     * @param  array<int, Model>  $approvers
     * @return SupportCollection<int, Approval>
     */
    public function requestApprovalChain(array $approvers, ?Model $requester = null): SupportCollection
    {
        $created = collect();

        foreach (array_values($approvers) as $index => $approver) {
            $approval = new Approval([
                'ticket_id' => $this->getKey(),
                'approver_id' => $approver->getKey(),
                'requested_by' => $requester?->getKey() ?? auth()->id(),
                'status' => $index === 0 ? ApprovalStatus::PENDING->value : ApprovalStatus::WAITING->value,
                'position' => $index + 1,
            ]);
            $approval->save();

            if ($index === 0) {
                event(new ApprovalRequested($approval));
            }

            $created->push($approval);
        }

        return $created;
    }

    public function link(Ticket $other, LinkType|string $type, ?Model $user = null): TicketLink
    {
        if ($other->is($this)) {
            throw new InvalidArgumentException('A ticket cannot link to itself.');
        }

        $typeValue = $type instanceof LinkType ? $type->value : $type;
        $existing = $this->links()
            ->where('linked_ticket_id', $other->getKey())
            ->where('type', $typeValue)
            ->first();

        if ($existing instanceof TicketLink) {
            return $existing;
        }

        $link = new TicketLink([
            'ticket_id' => $this->getKey(),
            'linked_ticket_id' => $other->getKey(),
            'type' => $typeValue,
            'user_id' => $user?->getKey() ?? auth()->id(),
        ]);
        $link->save();

        return $link;
    }

    public function addChild(Ticket $child): TicketLink
    {
        return $this->link($child, LinkType::PARENT);
    }

    public function markDuplicateOf(Ticket $canonical): TicketLink
    {
        return $this->link($canonical, LinkType::DUPLICATE);
    }

    public function relate(Ticket $other): TicketLink
    {
        return $this->link($other, LinkType::RELATED);
    }

    public function childTickets(): Collection
    {
        return $this->linkedTickets(LinkType::PARENT);
    }

    public function parentTickets(): Collection
    {
        $ids = TicketLink::query()
            ->where('linked_ticket_id', $this->getKey())
            ->where('type', LinkType::PARENT->value)
            ->pluck('ticket_id');

        return static::query()->whereIn($this->getKeyName(), $ids)->get();
    }

    public function linkedTickets(LinkType|string $type): Collection
    {
        $typeValue = $type instanceof LinkType ? $type->value : $type;
        $ids = $this->links()->where('type', $typeValue)->pluck('linked_ticket_id');

        return static::query()->whereIn($this->getKeyName(), $ids)->get();
    }

    public function attachConfigurationItem(ConfigurationItem|int $item): void
    {
        $this->configurationItems()->syncWithoutDetaching([
            $item instanceof ConfigurationItem ? $item->getKey() : $item,
        ]);
    }

    public function setCustom(string $key, mixed $value): self
    {
        $definition = CustomFieldDefinition::query()->where('key', $key)->first();

        if ($definition) {
            if ($definition->ticket_type && $definition->ticket_type !== $this->type) {
                throw new InvalidArgumentException("Custom field [{$key}] does not apply to this ticket type.");
            }

            if ($definition->department_id && (int) $definition->department_id !== (int) $this->department_id) {
                throw new InvalidArgumentException("Custom field [{$key}] does not apply to this department.");
            }

            if ($definition->service_id && (int) $definition->service_id !== (int) $this->service_id) {
                throw new InvalidArgumentException("Custom field [{$key}] does not apply to this catalog item.");
            }

            $definition->assertValue($value);
        }

        $attributes = $this->custom_attributes ?? [];
        $attributes[$key] = $value;
        $this->custom_attributes = $attributes;
        $this->save();

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function missingRequiredCustomFields(): array
    {
        $values = $this->custom_attributes ?? [];

        return CustomFieldDefinition::query()
            ->where('is_required', true)
            ->where(function ($query) {
                $query->whereNull('ticket_type')->orWhere('ticket_type', $this->type);
            })
            ->where(function ($query) {
                $query->whereNull('department_id');

                if ($this->department_id) {
                    $query->orWhere('department_id', $this->department_id);
                }
            })
            ->where(function ($query) {
                $query->whereNull('service_id');

                if ($this->service_id) {
                    $query->orWhere('service_id', $this->service_id);
                }
            })
            ->get()
            ->filter(function (CustomFieldDefinition $definition) use ($values) {
                if (! array_key_exists($definition->key, $values)) {
                    return true;
                }

                return $values[$definition->key] === null || $values[$definition->key] === '';
            })
            ->pluck('key')
            ->all();
    }

    public function attachFile(UploadedFile $file, ?Model $user = null, ?Message $message = null): Attachment
    {
        $disk = (string) config('laravel_ticket.attachments.disk', 'public');
        $directory = trim((string) config('laravel_ticket.attachments.path', 'tickets'), '/').'/'.$this->getKey();
        $path = $file->store($directory, $disk);
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        $attachment = new Attachment([
            'ticket_id' => $this->getKey(),
            'message_id' => $message?->getKey(),
            'user_id' => $user?->getKey() ?? auth()->id(),
            'disk' => $disk,
            'path' => is_string($path) ? $path : '',
            'original_name' => $name !== '' ? $name : 'file',
            'mime' => $mime,
            'size' => $file->getSize() ?: 0,
        ]);
        $attachment->save();

        return $attachment;
    }

    public function isPending(): bool
    {
        return $this->status === Status::PENDING->value;
    }

    public function isInProgress(): bool
    {
        return $this->status === Status::IN_PROGRESS->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === Status::CANCELLED->value;
    }

    public function isActive(): bool
    {
        return in_array($this->status, Status::active(), true);
    }
}
