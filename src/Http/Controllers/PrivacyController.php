<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Http\DeskAccess;
use Coderflex\LaravelTicket\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);

        return view('laravel-ticket::desk.privacy', [
            'email' => (string) $actor->getAttribute('email'),
        ]);
    }

    public function export(Request $request, TicketDesk $desk): JsonResponse
    {
        $actor = $this->actor($request);
        $email = (string) $request->query('email', $actor->getAttribute('email'));
        $this->authorizeEmail($actor, $email);

        return response()->json($desk->exportForRequester($email, $actor));
    }

    public function erase(Request $request, TicketDesk $desk): RedirectResponse
    {
        $actor = $this->actor($request);
        $email = (string) $request->validate(['email' => ['required', 'email']])['email'];
        $this->authorizeEmail($actor, $email);
        $count = $desk->eraseRequester($email, $actor);

        return back()->with('status', "Erased {$count} tickets.");
    }

    private function authorizeEmail(Model $actor, string $email): void
    {
        $own = strcasecmp((string) $actor->getAttribute('email'), $email) === 0;

        if ($own || DeskAccess::manages($actor) || Department::agentDepartmentIds($actor) !== []) {
            return;
        }

        abort(403);
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
