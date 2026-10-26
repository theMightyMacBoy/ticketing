<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Coderflex\LaravelTicket\Exceptions\ReplyNotAllowed;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\RichText\MessageWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);

        $tickets = $this->owned($actor)
            ->latest('id')
            ->paginate(20);

        return view('laravel-ticket::portal.index', [
            'tickets' => $tickets,
        ]);
    }

    public function show(Request $request, int $ticket): View
    {
        $actor = $this->actor($request);
        $record = $this->owned($actor)->whereKey($ticket)->first();

        if (! $record instanceof Ticket) {
            abort(404);
        }

        Gate::authorize('view', $record);

        $messages = $record->messages()->with('attachments')->oldest('id')->get()->filter(function ($message) {
            return $message instanceof Message
                && $message->visibility === MessageVisibility::PUBLIC->value;
        });

        return view('laravel-ticket::portal.ticket', [
            'ticket' => $record,
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = $this->owned($actor)->whereKey($ticket)->first();

        if (! $record instanceof Ticket) {
            abort(404);
        }

        Gate::authorize('view', $record);

        try {
            app(MessageWriter::class)->write($record, $actor, $request, 'public');
        } catch (ReplyNotAllowed $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reply added.');
    }

    private function owned(Model $actor): Builder
    {
        $email = $actor->getAttribute('email');

        return Ticket::query()
            ->visibleTo($actor)
            ->where(function (Builder $query) use ($actor, $email) {
                $query->where($query->qualifyColumn('user_id'), $actor->getKey());

                if (is_string($email) && $email !== '') {
                    $query->orWhere($query->qualifyColumn('requester_email'), $email);
                }
            });
    }

    private function actor(Request $request): Model
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            abort(403);
        }

        return $user;
    }
}
