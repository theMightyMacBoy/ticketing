<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Access\TeamRoster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController
{
    public function opened(Request $request): View
    {
        return $this->index($request, false);
    }

    public function open(Request $request): View
    {
        return $this->index($request, true);
    }

    private function index(Request $request, bool $onlyOpen): View
    {
        $actor = $this->actor($request);
        $search = trim((string) $request->query('q', ''));
        $reports = TeamRoster::reportEmails($actor);

        $tickets = TeamRoster::tickets($actor, $onlyOpen)
            ->with('department')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('laravel-ticket::desk.team', [
            'tickets' => $tickets,
            'search' => $search,
            'onlyOpen' => $onlyOpen,
            'hasReports' => $reports !== [],
        ]);
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
