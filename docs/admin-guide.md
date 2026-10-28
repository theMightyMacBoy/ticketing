# Admin guide

This guide is for the person who installs Laravel Ticket, sets its configuration, and operates the service desk. People who file and work tickets should use the [user guide](user-guide.md).

The package runs inside a Laravel application you already have. It stores the tickets, enforces the workflow, reads Exchange Online, and serves the agent desk and the requester portal. Outbound mail uses the application's SMTP relay.

## Requirements

- PHP 8.1, 8.2, 8.3, or 8.4
- Laravel 9, 10, 11, 12, or 13 (`illuminate/contracts` ^9 through ^13)
- A users table with an `email` column
- The Laravel scheduler, if you want mail fetch, mailbox cleanup, or SLA evaluation to run on their own

The sign-in screen and the mailbox reader use HTTPS endpoints on Microsoft or another OpenID Connect provider. This package does not ship a Microsoft Graph library or a JWT library. Graph calls use Laravel's HTTP client. ID tokens are checked with OpenSSL.

## Install

```bash
composer require coderflex/laravel-ticket
php artisan vendor:publish --tag="ticket-config"
php artisan vendor:publish --tag="ticket-migrations"
php artisan migrate
```

Publish the config before you migrate when the default table names would collide with tables you already have. The config file is `config/laravel_ticket.php`.

The published migrations, in the order the package registers them:

| Migration | What it adds |
|---|---|
| `create_tickets_table` | Tickets |
| `create_messages_table` | Replies and notes |
| `create_categories_table` | Categories |
| `create_labels_table` | Labels |
| `create_category_ticket_table` | Category pivot |
| `create_label_ticket_table` | Label pivot |
| `add_assigned_to_column_into_tickets_table` | Assignee |
| `install_enterprise_itsm` | Types, numbers, SLA, groups, approvals, catalog, audit |
| `add_support_mailboxes` | Exchange mailboxes and stored messages |
| `add_departments` | Departments, department agents, calendars |
| `add_launch_desk` | Confidential flag, requester email, mailbox errors |
| `seed_demo_desk` | Demo organization, only when `TICKET_DEMO_SEED` is true |
| `add_single_sign_on` | Identity providers and per-address sign-in method |
| `add_team_managers` | Manager email and the Graph manager-sync flag |
| `add_group_manager_block` | Per-group block of the requester's manager |

`user_id` on tickets and messages stays required. A ticket always has an owning user, even when the person who wrote the email is stored separately as `requester_email`.

Add the ticket trait to your user model:

```php
use Coderflex\LaravelTicket\Concerns\HasTickets;
use Coderflex\LaravelTicket\Contracts\CanUseTickets;

class User extends Model implements CanUseTickets
{
    use HasTickets;
}
```

`HasTickets` provides `tickets()`, `messages()`, `assignedTickets()`, and `watchedTickets()`.

Platform administrators implement `Coderflex\LaravelTicket\Contracts\CanManageTickets` and return true from `canManageTickets()`, or you set `laravel_ticket.agents.callback`. Either path can manage every non-confidential ticket. Confidential tickets still require involvement. See [Who can see a ticket](#who-can-see-a-ticket).

## Routes

| Config | Default | Purpose |
|---|---|---|
| `routes.desk` | `desk` | Agent desk prefix. `/desk` is the queue. |
| `routes.portal` | `portal` | Requester portal prefix. |
| `routes.middleware` | `['web', 'auth']` | Middleware on the desk and the portal. |

Login routes are registered only when `auth.login` or `demo.login` is true. Both default to false, so a host application that already has a gate does not gain a second login page.

| Route name | Method and path | Who |
|---|---|---|
| `laravel-ticket.desk` | `GET /desk` | Signed-in user. The queue is filtered. |
| `laravel-ticket.tickets.show` | `GET /desk/tickets/{ticket}` | Someone allowed to view that ticket. |
| `laravel-ticket.tickets.reply` | `POST /desk/tickets/{ticket}/reply` | Someone allowed to update it. |
| `laravel-ticket.tickets.note` | `POST /desk/tickets/{ticket}/note` | Someone allowed to update it. |
| `laravel-ticket.tickets.assign` | `POST /desk/tickets/{ticket}/assign` | Someone allowed to update it. |
| `laravel-ticket.tickets.transfer` | `POST /desk/tickets/{ticket}/transfer` | Someone allowed to update it. |
| `laravel-ticket.tickets.status` | `POST /desk/tickets/{ticket}/status` | Someone allowed to update it. |
| `laravel-ticket.tickets.approve` | `POST /desk/tickets/{ticket}/approve` | Pending approver, or a platform administrator who is not blocked. |
| `laravel-ticket.team` | `GET /desk/team` | Any signed-in user. The list is their direct reports. |
| `laravel-ticket.team.open` | `GET /desk/team/open` | Same, excluding terminal statuses. |
| `laravel-ticket.catalog` | `GET /desk/catalog` | Signed-in user. |
| `laravel-ticket.catalog.store` | `POST /desk/catalog` | Signed-in user. |
| `laravel-ticket.portal` | `GET /portal` | Signed-in user. Own tickets only. |
| `laravel-ticket.portal.tickets.show` | `GET /portal/tickets/{ticket}` | The requester. |
| `laravel-ticket.portal.tickets.reply` | `POST /portal/tickets/{ticket}/reply` | The requester. |
| `laravel-ticket.setup` | `GET /desk/setup` | Department agent or platform administrator. |
| `laravel-ticket.directory` | `GET /desk/directory` | Department agent or platform administrator. |
| `laravel-ticket.privacy` | `GET /desk/privacy` | Any signed-in user can open their own export. Erasing someone else requires an agent or a platform administrator. |
| `laravel-ticket.login` | `GET /desk/login` | Public, when login is enabled. |

Ticket addresses take the numeric id, not the public ticket number.

A department agent can change groups, types, catalog items, and calendars for their own departments. Creating a department or a global ticket type requires a platform administrator. Any department agent can add a mailbox, including one with no department, and can add a sign-in provider. A group with no department can have its manager block changed only by a platform administrator.

## Who can see a ticket

`TicketPolicy` is registered for the ticket model.

**View** is allowed when the person manages tickets, or when they are engaged with that ticket. A confidential ticket is blocked unless they are engaged. Managing tickets is not enough.

**Update** (public reply, internal note, assign, transfer, status) is allowed for a platform administrator who is not blocked, the assignee, a member of the ticket's support group, or an agent of the ticket's department. Being the requester's manager does not grant update.

**Approve** is allowed for a platform administrator who is not blocked, or for the user named on a pending approval.

**Delete** is allowed for a platform administrator who is not blocked. The desk screens do not expose delete.

A person is engaged when they are any of:

- the owning user, the affected user, or the assignee
- a watcher
- a member of the ticket's assignment group
- an agent of the ticket's department
- the requester's manager, unless the current assignment group blocks managers
- the pending approver

The main queue uses `visibleTo`. For a platform administrator that is every non-confidential ticket, plus confidential tickets they are engaged with through the paths above except manager supervision. Manager supervision is not part of the queue query. A manager sees direct reports on the team pages, not mixed into **Open work**.

## Configuration reference

All keys live under `laravel_ticket` in `config/laravel_ticket.php`.

### Tables and models

`table_names` renames every table the package creates. Nested message, label, and category pivots also rename the foreign-key columns. Change these before the first migration.

`models` replaces a package model with your subclass. The desk resolves relations through this map. Point `auth.providers.users.model` at your user class. The package reads that for group members, department agents, and sign-in lookup.

### Numbering

```php
'numbering' => [
    'pad' => 6,
    'prefixes' => [
        'incident' => 'INC',
        'service_request' => 'REQ',
        'problem' => 'PRB',
        'change' => 'CHG',
    ],
],
```

A new ticket receives a public number when it is opened. The built-in types use those prefixes: `INC-000001`. Each type has its own sequence. A department type uses the prefix you set on that type, such as `CASE` or `WO`, and its own sequence. `pad` is the zero-fill width.

### Workflow

`workflow.require_change_approval` defaults to true. A change ticket cannot move to `in_progress` until it has approvals and every one of them is approved. Set it to false to record change approvals without blocking that move.

A department type with **Requires approval** checked uses the same gate, including types that are not changes. If the gate applies and the ticket has no approval rows, the move to `in_progress` is refused.

Legal status moves:

| From | To |
|---|---|
| new | open, in_progress, pending, resolved, closed, cancelled, archived |
| open | in_progress, pending, resolved, closed, cancelled, archived |
| in_progress | open, pending, resolved, closed, cancelled, archived |
| pending | open, in_progress, resolved, closed, cancelled, archived |
| resolved | open, in_progress, pending, closed, archived |
| closed | open, in_progress, resolved, archived |
| cancelled | open, archived |
| archived | open, closed |

Moving to `resolved` or `closed` also requires every required custom field. A new ticket created through Eloquent defaults to status `new` when you do not set one.

### Agents

```php
'agents' => [
    'callback' => null,
],
```

Return true from the callback for users who manage every non-confidential ticket:

```php
'callback' => fn ($user) => $user->email === 'admin@example.com',
```

Users that implement `CanManageTickets` are agents when `canManageTickets()` returns true, even when the callback is null. Department agents are a separate list. They are stored on the department, not by this callback.

### Business hours

```php
'business_hours' => [
    'timezone' => 'UTC',
    'week' => [
        'monday' => ['start' => '09:00', 'end' => '17:00'],
        // tuesday through friday the same way
    ],
    'holidays' => [],
],
```

This is the fallback calendar for an SLA policy that uses business hours and whose department has no calendar of its own. Times are interpreted in `timezone`. `holidays` is a list of `Y-m-d` dates. Saturday and Sunday are off when they are absent from `week`.

The setup screen's **Save weekday hours** writes Monday–Friday 09:00–17:00 in the timezone you type. Holidays and other hours are set with `Department::setCalendar()`.

### Attachments

```php
'attachments' => [
    'disk' => 'public',
    'path' => 'tickets',
    'max_kilobytes' => 10240,
],
```

`Ticket::attachFile()` stores an uploaded file on that disk. The ticket page, the internal note, and the portal reply share one composer. **Bold**, **Italic**, **List**, **Numbered**, and **Link** format the text. **Photo** uploads a JPEG, PNG, GIF, or WebP and places it in the text. **Attach files** adds up to ten files (images, PDF, text, CSV, ZIP, Word, or Excel). A file can be sent with no text. `max_kilobytes` is the limit for one file. The default is 10 MB.

Photos and files are downloaded through a signed-in route on that ticket. A photo in an internal note is not shown to the requester, and the requester cannot open its address. A photo in a public reply is part of the reply the requester sees. The default disk is `public`. If `public/storage` is linked, files on that disk can be opened without signing in. A desk that handles confidential tickets should set `disk` to `local`.

Publish and run `add_rich_messages` after the earlier migrations. It adds `messages.format` (`plain` or `html`) and `attachments.is_inline`.

### Mail

```php
'mail' => [
    'schedule' => true,
    'mailer' => null,
    'fetch_limit' => 100,
    'purge_limit' => 2000,
    'alert_to' => env('TICKET_MAIL_ALERT_TO'),
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
```

| Key | Meaning |
|---|---|
| `schedule` | When true, `tickets:fetch-mail` runs every minute and `tickets:purge-mail` runs at 00:00 UTC. The host must run `schedule:work` or a system cron for `schedule:run`. |
| `mailer` | Laravel mailer name for outbound replies. Null uses the default mailer. |
| `fetch_limit` | Maximum messages read from one mailbox in one fetch. Pages use `$top` of at most 50 until this cap. |
| `purge_limit` | Maximum messages deleted from one mailbox in one purge. |
| `alert_to` | Address emailed when a mailbox fetch fails. Empty skips the alert. The failure is still stored on the mailbox as `last_error`. |
| `system_user_id` | User id stored on a ticket when the sender has no account. |
| `resolve_sender` | Callback that receives the inbound email and returns a user id. Used when the sender has no account and `system_user_id` is empty. |
| `token_url` | Graph token URL. `%s` is the tenant id. |
| `graph_url` | Graph base URL. |
| `graph.tenant_id`, `client_id`, `client_secret` | Fallback application credentials. A mailbox with its own tenant, client id, and secret uses those instead. |
| `graph.scope` | Client-credentials scope. The default is `https://graph.microsoft.com/.default`. |

Unknown senders are resolved in this order: a user with that email, then `system_user_id`, then `resolve_sender`, then `UnmatchedSender` is thrown and that message is not stored. `requester_email`, `sender_email`, and `sender_name` are always stored when the message is accepted. `user_id` is never left null.

The Graph app registration is separate from the sign-in app registration. Mail read needs application permission `Mail.Read`. Deleting old mail needs `Mail.ReadWrite`. If the tenant uses an application access policy, include every mailbox the desk should open.

### Notifications

```php
'notifications' => [
    'mail' => true,
],
```

When true, the desk emails:

- the requester, when a ticket is opened, with the ticket number
- the assignee, when a ticket is assigned
- the approver, when an approval is requested
- the assignee, when an SLA breach is newly flagged

A public reply from someone other than the requester is sent by the ticket mailer even when this flag is false. The acknowledgement is a notice, not a row in the conversation. Internal notes are never mailed.

### Demo

```php
'demo' => [
    'seed' => env('TICKET_DEMO_SEED', false),
    'password' => env('TICKET_DEMO_PASSWORD', 'password'),
    'login' => env('TICKET_DEMO_LOGIN', false),
],
```

`seed` inserts departments, groups, inactive mailboxes, calendars, and catalog rows during `seed_demo_desk`. It does not insert users. Users and the three sample tickets are created by `php artisan tickets:seed-demo`, because a package migration can run before the application's users table exists.

Leave `seed` false in production. Leave `login` false when the host application has its own gate. `login` exposes the same `/desk/login` screen as `auth.login`.

### Sign-in

```php
'auth' => [
    'login' => env('TICKET_AUTH_LOGIN', false),
],
```

`true` exposes `/desk/login`. The person enters an email. Discovery then decides:

1. An identity row with method `local` always gets the password page, even when the domain has a provider. This is the break-glass override.
2. An active provider whose domain list contains the email's domain sends the browser to that provider with `login_hint`.
3. An identity row with method `sso` uses its linked provider when that provider is still active.
4. Any other existing account gets the password page.
5. Anything else is rejected. No account is created.

A password posted for an SSO address is ignored and the browser goes to the provider. A local account that posts an email without a password is sent to the password page, and the email is stored in the session. A later password post must use that same email.

### Environment variables

| Variable | Config | Default |
|---|---|---|
| `TICKET_MAIL_ALERT_TO` | `mail.alert_to` | unset |
| `TICKET_GRAPH_TENANT_ID` | `mail.graph.tenant_id` | unset |
| `TICKET_GRAPH_CLIENT_ID` | `mail.graph.client_id` | unset |
| `TICKET_GRAPH_CLIENT_SECRET` | `mail.graph.client_secret` | unset |
| `TICKET_DEMO_SEED` | `demo.seed` | false |
| `TICKET_DEMO_PASSWORD` | `demo.password` | `password` |
| `TICKET_DEMO_LOGIN` | `demo.login` | false |
| `TICKET_AUTH_LOGIN` | `auth.login` | false |

Mailbox secrets and identity-provider secrets are encrypted with `APP_KEY`. Rotating the key without re-encrypting those columns makes Graph and sign-in fail.

## Departments

One ticket table serves every department. IT, HR, Facilities, and any later desk share it. A department has its own agents, ticket types and numbers, catalog, business calendar, and approval habit.

On **Setup**, **Add department** asks for a name. The slug is generated from the name. Only a platform administrator can add a department.

From code:

```php
$hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr', 'is_active' => true]);

$hr->addAgent($user);   // can view and update this department, including confidential tickets
$hr->addMember($user);  // associated, but not an agent

$hr->defineType('Case', 'CASE');                 // numbers CASE-000001
$hr->defineType('Change', 'CHG', true);          // requires approval before in_progress

$hr->setCalendar([
    'monday' => ['start' => '09:00', 'end' => '17:00'],
    'tuesday' => ['start' => '09:00', 'end' => '17:00'],
    'wednesday' => ['start' => '09:00', 'end' => '17:00'],
    'thursday' => ['start' => '09:00', 'end' => '17:00'],
    'friday' => ['start' => '09:00', 'end' => '17:00'],
], 'America/Detroit', ['2026-12-25']);
```

`transferTo()` moves the same ticket onto another department or support group and keeps the number. `spawnTask()` opens a child ticket. Reports can be limited with `ServiceDeskReport::forDepartment()`.

## Support groups

A support group is the queue inside a department: IT Network, HR Employee Relations, Facilities Buildings. **Add group** asks for a name and a department.

The first group connected to a mailbox becomes that mailbox's primary group. New mail opens there. You can pass primary explicitly:

```php
$group->connectMailbox($mailbox);
$group->connectMailbox($mailbox, primary: true);
$group->makePrimary();
$group->addMember($user);
```

Several groups may share one mailbox. New mail still opens on the single primary group. A reply stays on the ticket it belongs to and does not change `assignment_group_id` or `mailbox_id`. Every public reply from those groups is sent as that shared address.

### Block the requester's manager

Each group has **Block the requester's manager**. Turn it on for a queue whose tickets a manager must not read. HR employee relations is the case this exists for: a conversation between an employee and HR stays with HR.

While the ticket's current assignment group has the flag:

- both team queues omit the ticket
- the manager cannot open it through the team relationship
- the team page does not hint that a hidden ticket exists

The flag is about the group the ticket is on now. Transferring it to a group that does not block managers puts it back on the team lists. A ticket with no group is visible to the manager. A ticket that only has an HR department, and a null group, is visible to the manager.

Someone who works the group, is an agent of the department, or is assigned the ticket can still open it. An HR agent who is also the employee's manager still opens the case from the department queue. It stays off that person's team lists.

The checkbox is stored with **Save** on that group's row. An unchecked box clears the block. **Add group** can set the block at creation. Department agents can change the flag only for groups in their departments.

The demo group HR Employee Relations is created with the block on. The sample ticket "Payroll question" is on that group.

## Ticket types

Built-in type slugs are `incident`, `service_request`, `problem`, and `change`.

**Add type** asks for a name, a prefix of at most 12 characters, an optional department, and **Requires approval**. A department agent can add a type only inside their departments. A type with no department is global and requires a platform administrator. The slug is the name, with underscores.

A department-specific type wins over a global type with the same slug when the ticket has that department.

## Priority

Impact and urgency are `low`, `medium`, `high`, or `critical`. `normal` is accepted as `medium` so older tickets still score. The score is impact weight times urgency weight (1, 2, 3, 4).

| Score | Priority |
|---|---|
| 12 or more | critical |
| 6–11 | high |
| 3–5 | medium |
| 1–2 | low |

`makePriorityAsLow()`, `makePriorityAsNormal()`, `makePriorityAsMedium()`, `makePriorityAsHigh()`, and `makePriorityAsCritical()` set the stored priority directly.

## Approvals

```php
$ticket->requestApproval($approver);
$ticket->requestApprovalChain([$manager, $director]);
```

The chain is ordered. The first approver is pending and is emailed when notifications are on. Later steps wait. Approving the active step makes the next step pending and emails that approver. Rejecting stops the chain. On the ticket, **Approve** posts `decision=approved` and **Reject** posts `decision=rejected`.

Pending pauses the SLA clock. `Ticket::pend()` records a reason of `customer`, `vendor`, `change`, or `other`. Leaving pending adds the paused minutes back and clears the reason. Business-hours policies count only working minutes.

## Catalog and custom fields

**Add catalog item** creates a service in a department, optionally on a support group, and optionally one required string field. Opening that service from **Catalog** routes the ticket onto the service's department and group.

Richer fields are created on `CustomFieldDefinition`:

| `data_type` | Form control |
|---|---|
| `string` | Text |
| `number` | Number |
| `boolean` | Checkbox |
| `date` | Date |
| `select` | A dropdown. Options live on the definition. |

Required fields must be present when the ticket is opened from the catalog, and again when it moves to resolved or closed. `setCustom('hostname', 'ada-laptop')` stores a value. `missingRequiredCustomFields()` lists what is still empty.

The catalog form includes **Confidential**. `is_confidential` hides the ticket from a platform administrator who is not involved.

## SLA

There is no SLA form on the setup screen. Create a policy in code:

```php
SlaPolicy::query()->create([
    'name' => 'Priority 1',
    'department_id' => $it->getKey(),
    'priority' => 'critical',
    'ticket_type' => 'incident',
    'response_minutes' => 15,
    'resolution_minutes' => 240,
    'use_business_hours' => true,
    'is_default' => false,
]);
```

`tickets:evaluate-slas` refreshes breach flags on tickets that still have an SLA and are new, open, in progress, or pending. The package does not schedule this command. Run it every minute yourself. A new breach fires `TicketSlaBreached` and, when notifications are on, emails the assignee.

The first public reply from someone other than the requester stamps the first response. The requester's own reply, an inbound customer email, and an internal note do not.

`ServiceDeskReport` exposes:

- `backlog()` — active tickets
- `breached()` — active tickets with a response or resolution breach
- `slaAttainment()` — percent of resolved or closed tickets that met the resolution target, or null when none have finished
- `meanTimeToResolveMinutes()` — average from creation to `resolved_at`
- `forDepartment($department)` — the same figures for one department

## Organizations, categories, labels, links, work, and configuration items

These are model APIs. The desk screens do not edit them.

| API | What it stores |
|---|---|
| `associateOrganization()` | The requester's organization. |
| `attachCategories()` / `syncCategories()` | Categories. A category has `is_visible`. |
| `attachLabels()` / `syncLabels()` | Labels. A label has `is_visible`. |
| `link($other, 'related')` | A link. Types are `related`, `parent`, `duplicate`, and `caused_by`. |
| `addChild()`, `markDuplicateOf()`, `relate()` | Shortcuts for those link types. |
| `watch()` / `unwatch()` | Watchers, who can view the ticket. |
| `logWork($minutes, $description)` | A work log. |
| `affect($user)` | The person affected, when that is not the requester. |
| `attachConfigurationItem()` | A configuration item related to the ticket. |
| `ResolutionCode` | Optional code stored by `resolve($code)`. |

`message()` and `messageAsUser()` add a public reply. `note()` adds an internal note. Both accept an optional format, `plain` or `html`. The desk stores a reply with no formatting as plain text. Formatted text is stored as HTML after unsafe tags, scripts, and remote images are removed. A public reply on an archived or cancelled ticket throws `ReplyNotAllowed`. A locked ticket accepts a public reply from the assignee or a platform administrator.

Every status change, reply, and note writes a `TicketActivity` row.

## Directory

**Directory** lists staff profiles and can save a person:

| Field | Effect |
|---|---|
| Name | Display name. |
| Email | Account email. An account is created when one does not exist. The password is the demo password when demo login is how you are seeding; otherwise a random password is stored. Company users sign in through the provider, so they do not use that password. |
| Employee number | Stored on the profile. |
| Department | Optional. When a department is selected, the person is attached to it. Saving with no department leaves existing memberships alone. |
| Agent | With a department selected, checked grants agent rights and unchecked leaves them in the department without agent rights. |
| Sign-in | `Detect from the email domain` deletes any override. `Local password` forces the password page. `Single sign-on` pins the address to the provider for its domain and fails when that domain has no provider. |

A new account created from the directory gets `demo.password` (default `password`). If the user model casts `password` as `hashed`, the plain value is stored and Laravel hashes it. An existing account keeps its password. Single sign-on accounts created at first login get a random password instead, because those people authenticate at the provider.

Agent rights always come from the directory or from `addAgent()`. The identity provider does not send them.

## Sign-in providers

**Add sign-in provider** on Setup stores an OpenID Connect client.

| Field | Rules |
|---|---|
| Name | Display name. The slug is generated from it. |
| Email domain | Letters, digits, dots, and hyphens. Example: `contoso.com`. One domain per provider form. Stored lowercased. |
| Issuer | HTTPS URL. For Entra ID use `https://login.microsoftonline.com/{tenant}/v2.0`. |
| Client id | The sign-in app's application id. |
| Client secret | Stored encrypted. It is never written back to the page. |
| Sync each person's manager from Microsoft Graph | Default on in the form. See [Managers](#managers). |

Register this redirect URI on the sign-in app:

```text
https://your-host/desk/login/sso/{slug}/callback
```

The sign-in app is not the mailbox Graph app. A department agent can save a provider. The secret is encrypted and is not shown again.

The desk loads discovery from the issuer, then the authorization, token, and JWKS URLs. Those three URLs must be HTTPS. The discovery document's issuer must match the configured issuer, ignoring a trailing slash. Discovery and JWKS are cached for one hour. A bad signature or an unknown `kid` is retried once with a fresh JWKS.

The ID token must be RS256. The desk checks issuer, audience (a string or a list), `exp`, `nbf` with 120 seconds of clock skew, and the nonce from the login session. The email claim (`email`, then `preferred_username`, then `upn`) must be the address the person typed. The state is single-use. PKCE S256 is sent.

A failed token request reports the OAuth error code or the HTTP status. The client secret and the response body are not shown.

The first successful sign-in creates the user with a random 40-character password and links an identity row. A later sign-in reuses that user. An identity row marked `local` is left as local.

## Managers

On each successful single sign-on, after the identity is linked, the desk stores the person's manager.

1. An ID token claim `manager_email` wins. A `manager` claim wins when it is an email, or an object with `email`, `mail`, or `userPrincipalName`. Graph is not called when a claim is present.
2. Otherwise, if **Sync each person's manager from Microsoft Graph** is on and the issuer is `https://login.microsoftonline.com/{tenant}` or the same URL with `/v2.0`, the desk calls Graph.
3. Graph uses client credentials on that same sign-in app: token scope `https://graph.microsoft.com/.default`, then `GET /v1.0/users/{id}/manager?$select=mail,userPrincipalName`. The object id is preferred when it is present. `mail` is preferred over `userPrincipalName`.
4. The sign-in app needs application permission `User.Read.All`. Admin consent is required. This permission is separate from `Mail.Read` on the mailbox app.

A Graph failure, including HTTP 403, does not fail sign-in and does not clear a manager already stored. HTTP 404 means the person has no manager, and the stored manager is cleared. HTTP 401 drops the cached token and retries once. The token is cached per provider. A person who is their own manager is stored with no manager.

Direct reports are identity rows whose `manager_email` equals the signed-in user's email, compared in lowercase. Skip-level reports are not included. Many people can share one manager. `manager_email` is not unique.

The manager then has the two team queues described in the [user guide](user-guide.md). Those queues exclude tickets whose current group has **Block the requester's manager**.

## Mailboxes

**Add inactive mailbox** stores a name, address, optional department, and optional retention days. The mailbox stays inactive. Fetch ignores inactive mailboxes. The setup screen does not ask for the Graph secret. Set credentials before you activate:

```php
$mailbox->forceFill([
    'tenant_id' => '...',      // omit to use TICKET_GRAPH_TENANT_ID
    'client_id' => '...',
    'client_secret' => '...',  // encrypted at rest
    'is_active' => true,
    'delete_read_after_days' => 30, // omit to keep mail
])->save();

$network->connectMailbox($mailbox);
```

Empty per-mailbox credentials fall back to `mail.graph`.

### What fetch does

`tickets:fetch-mail` reads unread mail for every active mailbox, or one mailbox when you pass `--mailbox=` as an id or an address.

- Authentication is OAuth client credentials. Basic auth is not used.
- The unread filter is `isRead eq false`. If Graph returns HTTP 400 for that filter, the page is retried once without it, and messages already marked read are skipped in PHP.
- Pages follow `@odata.nextLink` up to `fetch_limit`. A URL is not treated as seen until its response succeeds.
- The message is marked read after it is stored.
- The same Graph id is not processed twice. Uniqueness is `(mailbox_id, graph_id)`.
- Mail whose From is the mailbox itself is skipped, so a reply the desk sent does not open another ticket.
- A fetch with no primary group fails. The error is stored on `last_error` and `last_error_at`. When `mail.alert_to` is set, that address is emailed. The secret is not included.

New mail opens a ticket on the primary group, with channel email. The subject becomes the title. The body becomes the opening message.

A reply is matched, in order:

1. `In-Reply-To` and `References`, after angle brackets are stripped and the id is lowercased, against `internet_message_id` stored for that mailbox.
2. A ticket number in the subject whose prefix is one of `numbering.prefixes` (`INC`, `REQ`, `PRB`, `CHG` by default). A department prefix such as `CASE` is not matched from the subject. Those replies still attach when `In-Reply-To` or `References` hits a stored message id.

The reply does not move the ticket to another group.

### Outbound

Public replies go out through SMTP (`mail.mailer` or the default mailer). The From address is the ticket's mailbox, or else the assignment group's mailbox. The To address is `requester_email`, or else the owning user's email. The subject is `[TICKET-NUMBER] title`. `In-Reply-To` and `References` are set from the last stored internet message ids. A reply with formatting or a photo is sent as HTML, and each photo is embedded in that message. Other files on the reply are attached. A reply with no formatting stays plain text.

Nothing is sent when:

- the message is an internal note
- the author is the ticket's owning user
- the inbound path set the message to suppress outbound mail
- To and From are the same address
- the ticket has no mailbox

### Retention

`delete_read_after_days` is optional. When it is set, `tickets:purge-mail` deletes read inbox mail older than that many days. The cutoff is 00:00 UTC on the current UTC day, minus the number of days. Unread mail is kept. A mailbox with no rule is skipped unless you target it with `--mailbox=`, and a targeted mailbox with no rule still deletes nothing.

00:00 UTC is 8:00 PM in America/Detroit the previous evening while daylight saving time is in effect, and 7:00 PM after it ends.

A delete that Graph answers with 404 counts as already gone. A 403 throws `MailboxMisconfigured`, names `Mail.ReadWrite`, and includes the Graph error code. It does not include the secret.

## Privacy

`GET /desk/privacy/export?email=` returns JSON. `POST /desk/privacy/erase` with `email` redacts matching tickets.

Any signed-in person can export and erase their own address, including confidential tickets they opened. The Privacy link is on the rail for department agents and platform administrators. The address `/desk/privacy` itself accepts any signed-in user.

A department agent exporting or erasing someone else receives every non-confidential ticket for that email, in every department, plus confidential tickets in the departments where they are an agent. A platform administrator who is not that person and not a department agent receives only the non-confidential tickets. Another department's confidential tickets stay out of that export and are not erased.

Erase keeps the row and the ticket number. The title becomes "Erased request". The body, requester email, and custom attributes are cleared. Every message body becomes `[erased]`, and the sender name and email on those messages are cleared.

Internal notes are included in an export only when the caller is allowed to write an internal note on that ticket. A requester's export contains public messages.

## Demo data

```bash
php artisan tickets:seed-demo
```

The command creates the organization if the migration did not, then these accounts. The password is `password` unless you changed `TICKET_DEMO_PASSWORD`.

| Email | Role |
|---|---|
| `ada@example.com` | Requester. Files the sample tickets. |
| `it.agent@example.com` | Agent in IT. |
| `hr.agent@example.com` | Agent in HR. |
| `facilities.agent@example.com` | Agent in Facilities. |
| `manager@example.com` | Directory contact. Not an agent. Not automatically anyone's manager. |

Inactive mailboxes: `help@example.com`, `hr@example.com`, `facilities@example.com`.

Sample tickets:

| Title | Type | Group | Confidential |
|---|---|---|---|
| VPN is down for Ada | Incident `INC` | IT Network | No |
| Payroll question | Case `CASE` | HR Employee Relations, manager block on | Yes |
| Badge for the west door | Work order `WO` | Facilities Buildings | No |

Demo addresses stay on local passwords until you add a provider for `example.com`. Do not point a production provider at `example.com`.

To show the team queues in a demo, store a manager on Ada's identity. The seeder does not do that, because the sample accounts are local:

```php
AuthIdentity::query()->updateOrCreate(['email' => 'ada@example.com'], [
    'method' => 'local',
    'manager_email' => 'manager@example.com',
]);
```

`manager@example.com` then sees the VPN and badge tickets on the team pages, and does not see "Payroll question".

## Commands

| Command | When it runs |
|---|---|
| `tickets:fetch-mail {--mailbox=}` | Every minute, when `mail.schedule` is true. |
| `tickets:purge-mail {--mailbox=}` | Daily at 00:00 UTC, when `mail.schedule` is true. |
| `tickets:evaluate-slas` | Not scheduled. Add it to your scheduler if you use SLA policies. |
| `tickets:seed-demo` | Manual. Loads the demo desk. |

Fetch and purge use `withoutOverlapping`.

## Events

Listen on the Laravel event dispatcher. The package already listens to four of these for mail.

| Event | When |
|---|---|
| `TicketOpened` | A ticket is created. |
| `TicketStatusChanged` | The status changes. |
| `TicketAssigned` | `assigned_to` changes. |
| `TicketSlaBreached` | A response or resolution breach flips to true. |
| `ApprovalRequested` | An approval row is opened. |
| `ApprovalDecided` | An approver accepts or rejects. |

Use these for Slack or a webhook. The package's own listeners send the emails described under Notifications.

## Database notes

MySQL and SQLite allow several rows with a null `graph_id` under the unique `(mailbox_id, graph_id)` index. SQL Server treats those nulls as duplicates. Outbound messages store a null `graph_id`, so more than one outbound row for the same mailbox can fail on SQL Server. The same pattern is why `(identity_provider_id, subject)` is not a unique index. Sign-in collision is enforced in code.

`manager_email` is indexed and is not unique.

`lockForUpdate()` during numbering is a no-op on SQLite. On MySQL and SQL Server it serializes the sequence.

## What the desk screens cover

The web UI is the queue, the ticket (formatted reply, internal note, photos, files, assign, transfer, status, approve), the catalog, the two team lists, setup, the directory, privacy, the portal, and the email-first sign-in screen. **Dark mode** on the rail and the sign-in page stores the choice in the browser. There is no server setting. With no choice stored, the pages follow the device color scheme.

SLA policies, organizations, categories, labels, ticket links, work logs, configuration items, and resolution codes are available on the models. They do not have setup forms. Knowledge base articles, satisfaction surveys, on-call schedules, and live chat are not part of this package.

The original ticket model methods (`archive`, `close`, `reopen`, `assignTo`, the priority helpers, and the label and category relations) stay documented in the [README](../README.md).
