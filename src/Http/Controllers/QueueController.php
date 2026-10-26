<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $search = trim((string) $request->query('q', ''));

        $tickets = Ticket::query()
            ->visibleTo($actor)
            ->with('department')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('laravel-ticket::desk.queue', [
            'tickets' => $tickets,
            'search' => $search,
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
