<?php

use Coderflex\LaravelTicket\Models\Approval;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\Category;
use Coderflex\LaravelTicket\Models\ConfigurationItem;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\EmailMessage;
use Coderflex\LaravelTicket\Models\Label;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Organization;
use Coderflex\LaravelTicket\Models\ResolutionCode;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\SlaPolicy;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Models\TicketActivity;
use Coderflex\LaravelTicket\Models\TicketLink;
use Coderflex\LaravelTicket\Models\TicketSequence;
use Coderflex\LaravelTicket\Models\WorkLog;

return [
    /*
    |--------------------------------------------------------------------------
    | Tables Names
    |--------------------------------------------------------------------------
    |
    | You can change the table names depends on your application structure
    | the values in the below tables, is the current table name, and if
    | are happy with it, leave it as it is, if not. You may read the related
    | instruction before you change the values.
    |
    */
    'table_names' => [
        'tickets' => 'tickets',
        'categories' => 'categories',
        'labels' => 'labels',
        'messages' => [
            'table' => 'messages',
            'columns' => [
                'user_foreign_id' => 'user_id',
                'ticket_foreign_id' => 'ticket_id',
            ],
        ],
        'label_ticket' => [
            'table' => 'label_ticket',
            'columns' => [
                'label_foreign_id' => 'label_id',
                'ticket_foreign_id' => 'ticket_id',
            ],
        ],
        'category_ticket' => [
            'table' => 'category_ticket',
            'columns' => [
                'category_foreign_id' => 'category_id',
                'ticket_foreign_id' => 'ticket_id',
            ],
        ],
        'organizations' => 'organizations',
        'organization_user' => 'organization_user',
        'assignment_groups' => 'assignment_groups',
        'assignment_group_user' => 'assignment_group_user',
        'sla_policies' => 'sla_policies',
        'ticket_sequences' => 'ticket_sequences',
        'ticket_activities' => 'ticket_activities',
        'ticket_links' => 'ticket_links',
        'approvals' => 'approvals',
        'ticket_watchers' => 'ticket_watchers',
        'work_logs' => 'work_logs',
        'resolution_codes' => 'resolution_codes',
        'services' => 'services',
        'configuration_items' => 'configuration_items',
        'configuration_item_ticket' => 'configuration_item_ticket',
        'attachments' => 'attachments',
        'custom_field_definitions' => 'custom_field_definitions',
        'mailboxes' => 'mailboxes',
        'email_messages' => 'email_messages',
    ],

    'models' => [
        'ticket' => Ticket::class,
        'message' => Message::class,
        'category' => Category::class,
        'label' => Label::class,
        'organization' => Organization::class,
        'assignment_group' => AssignmentGroup::class,
        'sla_policy' => SlaPolicy::class,
        'ticket_sequence' => TicketSequence::class,
        'ticket_activity' => TicketActivity::class,
        'ticket_link' => TicketLink::class,
        'approval' => Approval::class,
        'work_log' => WorkLog::class,
        'resolution_code' => ResolutionCode::class,
        'service' => Service::class,
        'configuration_item' => ConfigurationItem::class,
        'attachment' => Attachment::class,
        'custom_field_definition' => CustomFieldDefinition::class,
        'mailbox' => Mailbox::class,
        'email_message' => EmailMessage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    |
    | Public ticket numbers are generated when a ticket is opened.
    | INC-000001, REQ-000001, PRB-000001, and CHG-000001.
    |
    */
    'numbering' => [
        'pad' => 6,
        'prefixes' => [
            'incident' => 'INC',
            'service_request' => 'REQ',
            'problem' => 'PRB',
            'change' => 'CHG',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    |
    | Change tickets cannot move into progress until an approver accepts
    | them. Set require_change_approval to false to record approvals
    | without blocking the transition.
    |
    */
    'workflow' => [
        'require_change_approval' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | Return true from the callback for users who can manage every ticket.
    | Users that implement CanManageTickets are agents when that method
    | returns true, even when this callback is null.
    |
    */
    'agents' => [
        'callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Business hours
    |--------------------------------------------------------------------------
    |
    | Used by SLA policies that opt into the business calendar. Times are
    | interpreted in the timezone below. Holidays are Y-m-d strings.
    |
    */
    'business_hours' => [
        'timezone' => 'UTC',
        'week' => [
            'monday' => ['start' => '09:00', 'end' => '17:00'],
            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
            'thursday' => ['start' => '09:00', 'end' => '17:00'],
            'friday' => ['start' => '09:00', 'end' => '17:00'],
        ],
        'holidays' => [],
    ],

    'attachments' => [
        'disk' => 'public',
        'path' => 'tickets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Support mailboxes
    |--------------------------------------------------------------------------
    |
    | Inbound mail is read from Exchange Online with application permissions
    | (client credentials). Mail.Read is required, plus an application access
    | policy when the tenant limits which mailboxes the app can open.
    | Outbound replies use the application's SMTP mailer. The From address
    | is the shared mailbox, including when several groups share it.
    | New mail opens on that mailbox's primary group.
    |
    | Unknown senders need system_user_id or a resolve_sender callback
    | that returns a user id. The callback receives the inbound email.
    |
    */
    'mail' => [
        'schedule' => true,
        'mailer' => null,
        'fetch_limit' => 25,
        'system_user_id' => null,
        'resolve_sender' => null,
        'token_url' => 'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
        'graph_url' => 'https://graph.microsoft.com/v1.0',
        'graph' => [
            'tenant_id' => env('TICKET_GRAPH_TENANT_ID'),
            'client_id' => env('TICKET_GRAPH_CLIENT_ID'),
            'client_secret' => env('TICKET_GRAPH_CLIENT_SECRET'),
            'scope' => 'https://graph.microsoft.com/.default',
        ],
    ],
];
