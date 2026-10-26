<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Coderflex\LaravelTicket\Exceptions\InvalidTicketTransition;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Exceptions\ReplyNotAllowed;
use Coderflex\LaravelTicket\Models\Approval;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\RichText\MessageWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketController
{
    public function show(Request $request, int $ticket): View
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::authorize('view', $record);

        $internal = Gate::forUser($actor)->allows('addInternalNote', $record);
        $messages = $record->messages()->with('attachments')->oldest('id')->get()->filter(function ($message) use ($internal) {
            return $message instanceof Message && ($internal || $message->visibility === 'public');
        });

        return view('laravel-ticket::desk.ticket', [
            'ticket' => $record,
            'messages' => $messages,
            'statuses' => Status::cases(),
            'departments' => Department::query()->orderBy('name')->get(),
            'groups' => AssignmentGroup::query()->orderBy('name')->get(),
            'canUpdate' => Gate::forUser($actor)->allows('update', $record),
            'canNote' => $internal,
            'canApprove' => Gate::forUser($actor)->allows('approve', $record),
        ]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::authorize('update', $record);

        try {
            app(MessageWriter::class)->write($record, $actor, $request, 'public');
        } catch (ReplyNotAllowed $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function note(Request $request, int $ticket): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::authorize('addInternalNote', $record);
        app(MessageWriter::class)->write($record, $actor, $request, 'internal');

        return back()->with('status', 'Note saved.');
    }

    public function assign(Request $request, int $ticket): RedirectResponse
    {
        $record = $this->ticket($ticket);
        Gate::authorize('update', $record);
        $email = (string) $request->validate(['email' => ['required', 'email']])['email'];
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            abort(500);
        }

        $assignee = $class::query()->where('email', $email)->first();

        if (! $assignee instanceof Model) {
            return back()->withErrors(['email' => 'No account uses that email.']);
        }

        $record->assignTo($assignee);

        return back()->with('status', 'Ticket assigned.');
    }

    public function transfer(Request $request, int $ticket): RedirectResponse
    {
        $record = $this->ticket($ticket);
        Gate::authorize('update', $record);

        if ($request->filled('assignment_group_id')) {
            $group = AssignmentGroup::query()->find($request->input('assignment_group_id'));

            if (! $group instanceof AssignmentGroup) {
                return back()->withErrors(['assignment_group_id' => 'Choose a support group.']);
            }

            $record->transferTo($group);
        } elseif ($request->filled('department_id')) {
            $department = Department::query()->find($request->input('department_id'));

            if (! $department instanceof Department) {
                return back()->withErrors(['department_id' => 'Choose a department.']);
            }

            $record->transferTo($department);
        }

        return back()->with('status', 'Ticket transferred.');
    }

    public function transition(Request $request, int $ticket): RedirectResponse
    {
        $record = $this->ticket($ticket);
        Gate::authorize('update', $record);
        $status = (string) $request->validate(['status' => ['required', 'string']])['status'];

        try {
            $record->update(['status' => $status]);
        } catch (InvalidTicketTransition|ApprovalRequired|MissingCustomFields $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status updated.');
    }

    public function approve(Request $request, int $ticket): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::authorize('approve', $record);

        $approval = $record->approvals()
            ->where('status', 'pending')
            ->where('approver_id', $actor->getKey())
            ->first();

        if (! $approval instanceof Approval && Gate::forUser($actor)->allows('update', $record)) {
            $approval = $record->approvals()->where('status', 'pending')->first();
        }

        if (! $approval instanceof Approval) {
            return back()->withErrors(['approval' => 'There is no approval waiting on you.']);
        }

        $decision = (string) $request->input('decision', 'approved');

        if ($decision === 'rejected') {
            $approval->reject((string) $request->input('comment', ''));
        } else {
            $approval->approve((string) $request->input('comment', ''));
        }

        return back()->with('status', 'Approval recorded.');
    }

    private function ticket(int $id): Ticket
    {
        $ticket = Ticket::query()->find($id);

        if (! $ticket instanceof Ticket) {
            abort(404);
        }

        return $ticket;
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
