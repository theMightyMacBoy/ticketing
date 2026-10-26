<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Http\DeskAccess;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\BusinessCalendar;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\TicketTypeDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class SetupController
{
    public function index(Request $request): View
    {
        $this->agent($request);

        return view('laravel-ticket::desk.setup', [
            'departments' => Department::query()->orderBy('name')->get(),
            'groups' => AssignmentGroup::query()->with('department')->orderBy('name')->get(),
            'mailboxes' => Mailbox::query()->orderBy('address')->get(),
            'services' => Service::query()->orderBy('name')->get(),
            'calendars' => BusinessCalendar::query()->orderBy('name')->get(),
            'providers' => IdentityProvider::query()->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);

        if (! DeskAccess::manages($actor)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
        ]);

        Department::query()->create([
            'name' => $data['name'],
            'slug' => $this->slug($data['slug'] ?? null, $data['name']),
        ]);

        return back()->with('status', 'Department saved.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
            'department_id' => ['required', 'integer'],
        ]);

        $departmentId = $this->departmentId($actor, (int) $data['department_id']);

        AssignmentGroup::query()->create([
            'name' => $data['name'],
            'slug' => $this->slug($data['slug'] ?? null, $data['name']),
            'department_id' => $departmentId,
        ]);

        return back()->with('status', 'Support group saved.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'prefix' => ['required', 'string', 'max:12'],
            'department_id' => ['nullable', 'integer'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $departmentId = $request->filled('department_id')
            ? $this->departmentId($actor, (int) $data['department_id'])
            : null;

        if ($departmentId === null && ! DeskAccess::manages($actor)) {
            abort(403);
        }

        $department = $departmentId ? Department::query()->find($departmentId) : null;

        if ($department instanceof Department) {
            $department->defineType($data['name'], $data['prefix'], $request->boolean('requires_approval'));

            return back()->with('status', 'Ticket type saved.');
        }

        $type = new TicketTypeDefinition([
            'name' => $data['name'],
            'prefix' => strtoupper($data['prefix']),
            'slug' => Str::slug($data['name'], '_'),
            'requires_approval' => $request->boolean('requires_approval'),
            'is_active' => true,
        ]);
        $type->save();

        return back()->with('status', 'Ticket type saved.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'department_id' => ['required', 'integer'],
            'assignment_group_id' => ['nullable', 'integer'],
            'field_name' => ['nullable', 'string'],
            'field_key' => ['nullable', 'string'],
        ]);

        $departmentId = $this->departmentId($actor, (int) $data['department_id']);

        $service = Service::query()->create([
            'name' => $data['name'],
            'slug' => $this->slug(null, $data['name']),
            'department_id' => $departmentId,
            'assignment_group_id' => $data['assignment_group_id'] ?? null,
        ]);

        if (is_string($data['field_key'] ?? null) && $data['field_key'] !== '') {
            CustomFieldDefinition::query()->create([
                'name' => $data['field_name'] ?: $data['field_key'],
                'key' => $data['field_key'],
                'service_id' => $service->getKey(),
                'department_id' => $departmentId,
                'data_type' => 'string',
                'is_required' => true,
            ]);
        }

        return back()->with('status', 'Catalog item saved.');
    }

    public function storeCalendar(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'timezone' => ['required', 'string'],
        ]);

        $departmentId = $this->departmentId($actor, (int) $data['department_id']);
        $department = Department::query()->find($departmentId);

        if (! $department instanceof Department) {
            abort(404);
        }

        $day = ['start' => '09:00', 'end' => '17:00'];
        $department->setCalendar([
            'monday' => $day,
            'tuesday' => $day,
            'wednesday' => $day,
            'thursday' => $day,
            'friday' => $day,
        ], $data['timezone']);

        return back()->with('status', 'Calendar saved.');
    }

    public function storeMailbox(Request $request): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'address' => ['required', 'email'],
            'department_id' => ['nullable', 'integer'],
            'delete_read_after_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $departmentId = $request->filled('department_id')
            ? $this->departmentId($actor, (int) $data['department_id'])
            : null;

        Mailbox::query()->create([
            'name' => $data['name'],
            'address' => $data['address'],
            'department_id' => $departmentId,
            'is_active' => false,
            'delete_read_after_days' => $data['delete_read_after_days'] ?? null,
        ]);

        return back()->with('status', 'Mailbox saved. It stays inactive until credentials are added.');
    }

    public function storeIdentity(Request $request): RedirectResponse
    {
        $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'domain' => ['required', 'string', 'regex:/^[A-Za-z0-9.-]+$/'],
            'issuer' => ['required', 'url', 'starts_with:https://'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ]);

        $provider = new IdentityProvider([
            'name' => $data['name'],
            'slug' => $this->slug(null, $data['name']),
            'domains' => [strtolower($data['domain'])],
            'issuer' => rtrim($data['issuer'], '/'),
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'is_active' => true,
        ]);

        try {
            $provider->save();
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sign-in provider saved.');
    }

    private function agent(Request $request): Model
    {
        $user = $request->user();

        if (! $user instanceof Model || ! DeskAccess::canConfigure($user)) {
            abort(403);
        }

        return $user;
    }

    private function departmentId(Model $actor, int $departmentId): int
    {
        if (DeskAccess::manages($actor)) {
            return $departmentId;
        }

        if (! in_array($departmentId, Department::agentDepartmentIds($actor), true)) {
            abort(403);
        }

        return $departmentId;
    }

    private function slug(?string $slug, string $name): string
    {
        $value = is_string($slug) && trim($slug) !== '' ? $slug : $name;

        return Str::slug($value, '-');
    }
}
