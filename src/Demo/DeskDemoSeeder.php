<?php

namespace Coderflex\LaravelTicket\Demo;

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Directory\StaffDirectory;
use Coderflex\LaravelTicket\Enums\CustomFieldType;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

class DeskDemoSeeder
{
    public function seedOrganization(): void
    {
        $it = $this->department('IT', 'it');
        $hr = $this->department('HR', 'hr');
        $facilities = $this->department('Facilities', 'facilities');

        $hr->defineType('Case', 'CASE');
        $hr->defineType('Access', 'ACC', true);
        $facilities->defineType('Work Order', 'WO', false, 'work_order');

        $help = $this->mailbox('IT Help', 'help@example.com', $it);
        $hrMail = $this->mailbox('HR', 'hr@example.com', $hr);
        $facilitiesMail = $this->mailbox('Facilities', 'facilities@example.com', $facilities);

        $network = $this->group('IT Network', 'it-network', $it);
        $desktop = $this->group('IT Desktop', 'it-desktop', $it);
        $relations = $this->group('HR Employee Relations', 'hr-employee-relations', $hr);
        $buildings = $this->group('Facilities Buildings', 'facilities-buildings', $facilities);

        $this->share($network, $help, true);
        $this->share($desktop, $help, false);
        $this->share($relations, $hrMail, true);
        $this->share($buildings, $facilitiesMail, true);

        $vpn = $this->service('VPN', 'vpn', $it, $network);
        $hire = $this->service('New Hire', 'new-hire', $hr, $relations);
        $badge = $this->service('Badge Access', 'badge-access', $facilities, $buildings);

        $this->field($vpn, 'Hostname', 'hostname', CustomFieldType::STRING->value);
        $this->field($hire, 'Start date', 'start_date', CustomFieldType::DATE->value);
        $this->field($hire, 'Manager', 'manager', CustomFieldType::STRING->value);
        $this->field($badge, 'Building', 'building', CustomFieldType::STRING->value);

        $hr->setCalendar($this->week('09:00', '17:00'), 'America/Detroit');
        $facilities->setCalendar($this->week('08:00', '16:00', true), 'America/Detroit');
    }

    public function seedSamples(): void
    {
        $this->seedOrganization();

        $directory = app(StaffDirectory::class);
        $it = Department::query()->where('slug', 'it')->first();
        $hr = Department::query()->where('slug', 'hr')->first();
        $facilities = Department::query()->where('slug', 'facilities')->first();

        $ada = $directory->upsert('Ada Lovelace', 'ada@example.com', null, false, 'E100');
        $directory->upsert('IT Agent', 'it.agent@example.com', $it instanceof Department ? (int) $it->getKey() : null, true, 'E200');
        $directory->upsert('HR Agent', 'hr.agent@example.com', $hr instanceof Department ? (int) $hr->getKey() : null, true, 'E300');
        $directory->upsert('Facilities Agent', 'facilities.agent@example.com', $facilities instanceof Department ? (int) $facilities->getKey() : null, true, 'E400');
        $directory->upsert('Department Manager', 'manager@example.com', null, false, 'E500');

        $requester = $ada->user()->first();
        $network = AssignmentGroup::query()->where('slug', 'it-network')->first();
        $buildings = AssignmentGroup::query()->where('slug', 'facilities-buildings')->first();

        $this->ticket('VPN is down for Ada', [
            'type' => 'incident',
            'department_id' => $it instanceof Department ? $it->getKey() : null,
            'assignment_group_id' => $network instanceof AssignmentGroup ? $network->getKey() : null,
            'is_confidential' => false,
            'requester_email' => 'ada@example.com',
            'message' => 'Ada cannot reach the corporate VPN.',
        ], $requester instanceof Model ? $requester : null);

        $this->ticket('Payroll question', [
            'type' => 'case',
            'department_id' => $hr instanceof Department ? $hr->getKey() : null,
            'is_confidential' => true,
            'requester_email' => 'ada@example.com',
            'message' => 'Ada asked about a missing payroll line.',
        ], $requester instanceof Model ? $requester : null);

        $this->ticket('Badge for the west door', [
            'type' => 'work_order',
            'department_id' => $facilities instanceof Department ? $facilities->getKey() : null,
            'assignment_group_id' => $buildings instanceof AssignmentGroup ? $buildings->getKey() : null,
            'is_confidential' => false,
            'requester_email' => 'ada@example.com',
            'message' => 'The west door badge reader needs a new card.',
        ], $requester instanceof Model ? $requester : null);
    }

    private function department(string $name, string $slug): Department
    {
        $department = Department::query()->firstOrNew(['slug' => $slug]);
        $department->fill([
            'name' => $name,
            'is_active' => true,
        ]);
        $department->save();

        return $department;
    }

    private function mailbox(string $name, string $address, Department $department): Mailbox
    {
        $mailbox = Mailbox::query()->firstOrNew(['address' => $address]);
        $mailbox->fill([
            'name' => $name,
            'department_id' => $department->getKey(),
            'is_active' => false,
        ]);
        $mailbox->save();

        return $mailbox;
    }

    private function group(string $name, string $slug, Department $department): AssignmentGroup
    {
        $group = AssignmentGroup::query()->firstOrNew(['slug' => $slug]);
        $group->fill([
            'name' => $name,
            'department_id' => $department->getKey(),
            'is_active' => true,
        ]);
        $group->save();

        return $group;
    }

    private function share(AssignmentGroup $group, Mailbox $mailbox, bool $primary): void
    {
        if ($group->mailbox_id) {
            return;
        }

        $group->connectMailbox($mailbox, $primary);
    }

    private function service(string $name, string $slug, Department $department, AssignmentGroup $group): Service
    {
        $service = Service::query()->firstOrNew(['slug' => $slug]);
        $service->fill([
            'name' => $name,
            'department_id' => $department->getKey(),
            'assignment_group_id' => $group->getKey(),
            'is_active' => true,
        ]);
        $service->save();

        return $service;
    }

    private function field(Service $service, string $name, string $key, string $type): void
    {
        $field = CustomFieldDefinition::query()->firstOrNew(['key' => $key]);
        $field->fill([
            'name' => $name,
            'service_id' => $service->getKey(),
            'department_id' => $service->department_id,
            'data_type' => $type,
            'is_required' => true,
        ]);
        $field->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(string $title, array $attributes, ?Model $requester): void
    {
        if (Ticket::query()->where('title', $title)->exists()) {
            return;
        }

        app(TicketDesk::class)->open(array_merge($attributes, [
            'title' => $title,
            'user_id' => $requester?->getKey(),
        ]), $requester);
    }

    /**
     * @return array<string, array{start: string, end: string}>
     */
    private function week(string $start, string $end, bool $saturday = false): array
    {
        $day = ['start' => $start, 'end' => $end];
        $week = [
            'monday' => $day,
            'tuesday' => $day,
            'wednesday' => $day,
            'thursday' => $day,
            'friday' => $day,
        ];

        if ($saturday) {
            $week['saturday'] = ['start' => '09:00', 'end' => '13:00'];
        }

        return $week;
    }
}
