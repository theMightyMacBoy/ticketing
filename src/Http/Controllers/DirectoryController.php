<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Auth\SignInPreference;
use Coderflex\LaravelTicket\Directory\StaffDirectory;
use Coderflex\LaravelTicket\Http\DeskAccess;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\StaffProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DirectoryController
{
    public function index(Request $request): View
    {
        $this->agent($request);

        return view('laravel-ticket::desk.directory', [
            'profiles' => StaffProfile::query()->with('department')->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StaffDirectory $directory): RedirectResponse
    {
        $actor = $this->agent($request);
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'department_id' => ['nullable', 'integer'],
            'employee_number' => ['nullable', 'string'],
            'is_agent' => ['nullable', 'boolean'],
            'authentication' => ['nullable', 'in:inherit,local,sso'],
        ]);

        $departmentId = $request->filled('department_id') ? (int) $data['department_id'] : null;

        if ($departmentId !== null && ! DeskAccess::manages($actor)) {
            if (! in_array($departmentId, Department::agentDepartmentIds($actor), true)) {
                abort(403);
            }
        }

        $directory->upsert(
            $data['name'],
            $data['email'],
            $departmentId,
            $request->boolean('is_agent'),
            $data['employee_number'] ?? null,
        );

        if (is_string($data['authentication'] ?? null) && $data['authentication'] !== '') {
            try {
                app(SignInPreference::class)->apply($data['email'], $data['authentication']);
            } catch (InvalidArgumentException $exception) {
                return back()->withErrors(['authentication' => $exception->getMessage()]);
            }
        }

        return back()->with('status', 'Directory updated.');
    }

    private function agent(Request $request): Model
    {
        $user = $request->user();

        if (! $user instanceof Model || ! DeskAccess::canConfigure($user)) {
            abort(403);
        }

        return $user;
    }
}
