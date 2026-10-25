<?php

namespace Coderflex\LaravelTicket\Concerns;

use Coderflex\LaravelTicket\Contracts\CanManageTickets;
use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Exceptions\ReplyNotAllowed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait InteractsWithTicketRelations
{
    /**
     * Associate Labels into an existing ticket
     *
     * @param  mixed  $id
     * @param  bool  $touch
     * @return void
     */
    public function attachLabels($id, array $attributes = [], $touch = true)
    {
        $this->labels()->attach($id, $attributes, $touch);
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of the ticket model..
     *
     * @param  Collection|Model|array  $ids
     * @param  bool  $detaching
     * @return array
     */
    public function syncLabels($ids, $detaching = true)
    {
        return $this->labels()->sync($ids, $detaching);
    }

    /**
     * Associate Categories into an existing ticket
     *
     * @param  mixed  $id
     * @param  bool  $touch
     * @return void
     */
    public function attachCategories($id, array $attributes = [], $touch = true)
    {
        $this->categories()->attach($id, $attributes, $touch);
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of the ticket model..
     *
     * @param  Collection|Model|array  $ids
     * @param  bool  $detaching
     * @return array
     */
    public function syncCategories($ids, $detaching = true)
    {
        return $this->categories()->sync($ids, $detaching);
    }

    /**
     * Add a public reply to an existing ticket.
     */
    public function message(string $message): Model
    {
        return $this->messageAsUser(auth()->user(), $message);
    }

    /**
     * Add a public reply as a specific user.
     */
    public function messageAsUser(?Model $user, string $message): Model
    {
        $this->guardPublicReply($user);

        return $this->addMessage($user, $message, MessageVisibility::PUBLIC->value);
    }

    /**
     * Add an internal note. Notes do not count as a first response.
     */
    public function note(string $message, ?Model $user = null): Model
    {
        return $this->addMessage($user ?? auth()->user(), $message, MessageVisibility::INTERNAL->value);
    }

    protected function addMessage(?Model $user, string $message, string $visibility): Model
    {
        return $this->messages()->create([
            'user_id' => $user?->getKey() ?? auth()->id(),
            'message' => $message,
            'visibility' => $visibility,
        ]);
    }

    protected function guardPublicReply(?Model $user): void
    {
        if (in_array($this->status, [Status::ARCHIVED->value, Status::CANCELLED->value], true)) {
            throw new ReplyNotAllowed('This ticket is not open for replies.');
        }

        if ($this->is_locked && ! $this->bypassesLock($user)) {
            throw new ReplyNotAllowed('This ticket is locked.');
        }
    }

    protected function bypassesLock(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        $callback = config('laravel_ticket.agents.callback');

        if (is_callable($callback) && $callback($user)) {
            return true;
        }

        if ($user instanceof CanManageTickets && $user->canManageTickets()) {
            return true;
        }

        return (int) $user->getKey() === (int) $this->assigned_to;
    }
}
