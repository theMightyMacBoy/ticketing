<?php

namespace Coderflex\LaravelTicket\Directory;

use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\StaffProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffDirectory
{
    public function upsert(
        string $name,
        string $email,
        ?int $departmentId,
        bool $agent,
        ?string $employeeNumber = null,
    ): StaffProfile {
        $email = strtolower(trim($email));
        $user = $this->person($name, $email);

        $profile = StaffProfile::query()->firstOrNew(['email' => $email]);
        $profile->fill([
            'user_id' => $user->getKey(),
            'department_id' => $departmentId,
            'name' => $name,
            'employee_number' => $employeeNumber,
            'is_agent' => $agent,
        ]);
        $profile->save();

        if ($departmentId) {
            $department = Department::query()->find($departmentId);

            if ($department instanceof Department) {
                if ($agent) {
                    $department->addAgent($user);
                } else {
                    $department->addMember($user);
                }
            }
        }

        return $profile;
    }

    private function person(string $name, string $email): Model
    {
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new RuntimeException('The auth user model is not configured.');
        }

        $existing = $class::query()->where('email', $email)->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        /** @var Model $user */
        $user = new $class;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $this->password($user),
        ])->save();

        return $user;
    }

    private function password(Model $user): string
    {
        $plain = (string) config('laravel_ticket.demo.password', 'password');
        $casts = $user->getCasts();

        if (($casts['password'] ?? null) === 'hashed') {
            return $plain;
        }

        return Hash::make($plain);
    }
}
