<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController
{
    public function create(Request $request): View
    {
        $this->actor($request);
        $serviceId = $request->query('service');
        $service = is_numeric($serviceId) ? Service::query()->find($serviceId) : null;

        $fields = $service instanceof Service
            ? CustomFieldDefinition::query()->where('service_id', $service->getKey())->orderBy('name')->get()
            : collect();

        return view('laravel-ticket::desk.catalog', [
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'service' => $service instanceof Service ? $service : null,
            'fields' => $fields,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'title' => ['required', 'string'],
            'message' => ['nullable', 'string'],
            'fields' => ['nullable', 'array'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $custom = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        try {
            $ticket = app(TicketDesk::class)->open([
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'service_id' => $data['service_id'],
                'custom_attributes' => $custom,
                'is_confidential' => $request->boolean('is_confidential'),
                'requester_email' => $actor->getAttribute('email'),
                'user_id' => $actor->getKey(),
            ], $actor);
        } catch (MissingCustomFields $exception) {
            return back()->withInput()->withErrors(['fields' => $exception->getMessage()]);
        }

        return redirect()->route('laravel-ticket.tickets.show', $ticket);
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
