<p align="center">
    <img src="art/logo.png" alt="Laravisit Logo" width="300">
    <br><br>
</p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/coderflex/laravel-ticket.svg?style=flat-square)](https://packagist.org/packages/coderflex/laravel-ticket)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/coderflexx/laravel-ticket/run-tests.yml?branch=main&label=test)](https://github.com/coderflexx/laravel-ticket/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/coderflexx/laravel-ticket/phpstan.yml?branch=main&label=code%20style)](https://github.com/coderflexx/laravel-ticket/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/coderflex/laravel-ticket.svg?style=flat-square)](https://packagist.org/packages/coderflex/laravel-ticket)

- [Introduction](#introduction)
- [Installation](#installation)
- [Configuration](#configuration)
- [Preparing your model](#preparing-your-model)
- [Usage](#usage)
  - [Ticket Table Structure](#ticket-table-structure)
  - [Message Table Structure](#message-table-structure)
  - [Label Table Structure](#label-table-structure)
  - [Category Table Structure](#category-table-structure)
- [API Methods](#api-methods)
  - [Ticket API Methods](#ticket-api-methods)
  - [Ticket Relationship API Methods](#ticket-relationship-api-methods)
  - [Ticket Scopes](#ticket-scopes)
  - [Category \& Label Scopes](#category--label-scopes)
- [Handling File Upload](#handling-file-upload)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Introduction
Laravel Ticket is the domain for an IT service desk. It stores incidents, service requests, problems, and changes, and it enforces the workflow around them: public ticket numbers, impact and urgency, SLA clocks, queues, approvals, audit history, and a service catalog.

This package does not render a portal or send mail. Your application owns the screens and the notification delivery. The models raise events (`TicketOpened`, `TicketStatusChanged`, `TicketAssigned`, `TicketSlaBreached`, `ApprovalRequested`, `ApprovalDecided`) so you can hook mail, Slack, or a webhook in a listener. Schedule `php artisan tickets:evaluate-slas` every minute to flag breaches while a ticket is still open.

## Installation

You can install the package via composer:

```bash
composer require coderflex/laravel-ticket
```

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ticket-config"
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="ticket-migrations"
```
Before Running the migration, you may publish the config file, and make sure the current tables does not make a conflict with your existing application, and once you are happy with the migration table, you can run

```bash
php artisan migrate
```

## Preparing your model

Add `HasTickets` trait into your `User` model, along with `CanUseTickets` interface

```php
...
use Coderflex\LaravelTicket\Concerns\HasTickets;
use Coderflex\LaravelTicket\Contracts\CanUseTickets;
...
class User extends Model implements CanUseTickets
{
    ...
    use HasTickets;
    ...
}
```

## Usage

The Basic Usage of this package, is to create a `ticket`, then associate the `labels` and the `categories` to it.

You can associate as many as `categories`/`labels` into a single ticket.

Here is an example

```php
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Models\Category;
use Coderflex\LaravelTicket\Models\Label;

...
public function store(Request $request)
{
    /** @var User */
    $user = Auth::user();

    $ticket = $user->tickets()
                    ->create($request->validated());

    $category = Category::first();
    $label = Label::first();

    $ticket->attachCategories($category);
    $ticket->attachLabels($label);
    
    // or you can create the categories & the tickets directly by:
    // $ticket->categories()->create(...);
    // $ticket->labels()->create(...);

    return redirect(route('tickets.show', $ticket->uuid))
            ->with('success', __('Your Ticket Was created successfully.'));
}

public function createLabel()
{
    // If you create a label seperated from the ticket and wants to
    // associate it to a ticket, you may do the following.
    $label = Label::create(...);

    $label->tickets()->attach($ticket);

    // or maybe 
    $label->tickets()->detach($ticket);
}

public function createCategory()
{
    // If you create a category/categories seperated from the ticket and wants to
    // associate it to a ticket, you may do the following.
    $category = Category::create(...);

    $category->tickets()->attach($ticket);

    // or maybe 
    $category->tickets()->detach($ticket);
}
...
```

### Ticket Table Structure

| Column Name  | Type  |  Default  |
|---|---|---|
|  ID |`integer` | `NOT NULL`  |
|  UUID |`string` | `NULL`  |
|  user_id |`integer` | `NOT NULL`  |
|  title |`string` | `NOT NULL`  |
|  message |`string` | `NULL`  |
|  priority |`string` | `low`  |
|  status |`string` | `open`  |
|  is_resolved |`boolean` | `false`  |
|  is_locked |`boolean` | `false`  |
| assigned_to | `integer` | `NULL` |
|  created_at |`timestamp` | `NULL`  |
|  updated_at |`timestamp` | `NULL`  |

### Message Table Structure

| Column Name  | Type  |  Default  |
|---|---|---|
|  ID |`integer` | `NOT NULL`  |
|  user_id |`integer` | `NOT NULL`  |
|  ticket_id |`integer` | `NOT NULL`  |
|  message |`string` | `NULL`  |
|  created_at |`timestamp` | `NULL`  |
|  updated_at |`timestamp` | `NULL`  |

### Label Table Structure

| Column Name  | Type  |  Default  |
|---|---|---|
|  ID |`integer` | `NOT NULL`  |
|  name |`string` | `NULL`  |
|  slug |`string` | `NULL`  |
|  is_visible |`boolean` | `false`  |
|  created_at |`timestamp` | `NULL`  |
|  updated_at |`timestamp` | `NULL`  |

### Category Table Structure

| Column Name  | Type  |  Default  |
|---|---|---|
|  ID |`integer` | `NOT NULL`  |
|  name |`string` | `NULL`  |
|  slug |`string` | `NULL`  |
|  is_visible |`boolean` | `false`  |
|  created_at |`timestamp` | `NULL`  |
|  updated_at |`timestamp` | `NULL`  |

## API Methods

### Ticket API Methods
The `ticket` model came with handy methods to use, to make your building process easy and fast, and here is the list of the available __API__:

| Method               | Arguments  | Description                                   | Example                                             | Chainable |
|----------------------|---|-----------------------------------------------|-----------------------------------------------------|---|
| `archive`            |`void` | archive the ticket                            | `$ticket->archive()`                                | ✓
| `close`              |`void` | close the ticket                              | `$ticket->close()`                                  | ✓
| `reopen`             |`void` | reopen a closed ticket                        | `$ticket->reopen()`                                 | ✓
| `markAsResolved`     |`void` | mark the ticket as resolved                   | `$ticket->markAsResolved()`                         | ✓
| `markAsLocked`       |`void` | mark the ticket as locked                     | `$ticket->markAsLocked()`                           | ✓
| `markAsUnlocked`     |`void` | mark the ticket as unlocked                   | `$ticket->markAsUnlocked()`                         | ✓
| `markAsArchived`     |`void` | mark the ticket as archived                   | `$ticket->markAsArchived()`                         | ✓
| `closeAsResolved`    |`void` | close the ticket and marked it as resolved    | `$ticket->closeAsResolved()`                        | ✓
| `closeAsUnresolved`  |`void` | close the ticket and marked it as unresolved  | `$ticket->closeAsUnresolved()`                      | ✓
| `reopenAsUnresolved` |`void` | reopen the ticket and marked it as unresolved | `$ticket->reopenAsUnresolved()`                     | ✓
| `isArchived`         |`void` | check if the ticket archived                  | `$ticket->isArchived()`                             | ✗
| `isOpen`             |`void` | check if the ticket open                      | `$ticket->isOpen()`                                 | ✗
| `isClosed`           |`void` | check if the ticket closed                    | `$ticket->isClosed()`                               | ✗
| `isResolved`         |`void` | check if the ticket has a resolved status     | `$ticket->isResolved()`                             | ✗
| `isUnresolved`       |`void` | check if the ticket has an unresolved status  | `$ticket->isUnresolved()`                           | ✗
| `isLocked`           |`void` | check if the ticket is locked                 | `$ticket->isLocked()`                               | ✗
| `isUnlocked`         |`void` | check if the ticket is unlocked               | `$ticket->isUnlocked()`                             | ✗
| `assignTo`           |`void` | assign ticket to a user                       | `$ticket->assignTo($user)` or `$ticket->assignTo(2)` | ✓
| `makePriorityAsLow`  |`void` | make ticket priority as low                   | `$ticket->makePriorityAsLow()`                      | ✓
| `makePriorityAsNormal`|`void`| make ticket priority as normal                | `$ticket->makePriorityAsNormal()`                   | ✓
| `makePriorityAsHigh` |`void` | make ticket priority as high                  | `$ticket->makePriorityAsHigh()`                     | ✓

The __Chainable__ column, is showing the state for the method, that if it can be chained or not, something like
```php
    $ticket->archive()
            ->close()
            ->markAsResolved();
```
### Ticket Relationship API Methods
The `ticket` model has also a list of methods for interacting with another related models

| Method  | Arguments  |  Description  |  Example  |
|---|---|---|---|
|  `attachLabels` |`mixed` ID, `array` attributes, `bool` touch | associate labels into an existing ticket  | `$ticket->attachLabels([1,2,3,4])` |
|  `syncLabels` |`Model/array` IDs, `bool` detouching | associate labels into an existing ticket  | `$ticket->syncLabels([1,2,3,4])` |
|  `attachCategories` |`mixed` ID, `array` attributes, `bool` touch | associate categories into an existing ticket  | `$ticket->attachCategories([1,2,3,4])` |
|  `syncCategories` |`Model/array` IDs, `bool` detouching | associate categories into an existing ticket  | `$ticket->syncCategories([1,2,3,4])` |
|  `message` |`string` message | add new message on an existing ticket  | `$ticket->message('A message in a ticket')` |
|  `messageAsUser` |`Model/null` user, `string` message | add new message on an existing ticket as a different user  | `$ticket->messageAsUser($user, 'A message in a ticket')` |

> The `attachCategories` and `syncCategories` methods, is an alternative for `attach` and `sync` laravel methods, and if you want to learn more, please take a look at this [link](https://laravel.com/docs/9.x/eloquent-relationships#attaching-detaching)

The `commentAsUser` accepts a user as a first argument, if it's null, the __authenticated__ user will be user as default.

### Ticket Scopes
The `ticket` model has also a list of scopes to begin filter with.

| Method  | Arguments  |  Description  |  Example  |
|---|---|---|---|
|  `closed` |`void` | get the closed tickets  | `Ticket::closed()->get()` |
|  `opened` |`void` | get the opened tickets  | `Ticket::opened()->get()` |
|  `archived` |`void` | get the archived tickets  | `Ticket::archived()->get()` |
|  `unArchived` |`void` | get the unArchived tickets  | `Ticket::unArchived()->get()` |
|  `resolved` |`void` | get the resolved tickets  | `Ticket::resolved()->get()` |
|  `locked` |`void` | get the locked tickets  | `Ticket::locked()->get()` |
|  `unlocked` |`void` | get the unlocked tickets  | `Ticket::unlocked()->get()` |
|  `withLowPriority` |`void` | get the low priority tickets  | `Ticket::withLowPriority()->get()` |
|  `withNormalPriority` |`void` | get the normal priority tickets  | `Ticket::withNormalPriority()->get()` |
|  `withHighPriority` |`void` | get the high priority tickets  | `Ticket::withHighPriority()->get()` |
|  `withPriority` |`string` $priority | get the withPriority tickets  | `Ticket::withPriority('critical')->get()` |

### Category & Label Scopes
| Method  | Arguments  |  Description  |  Example  |
|---|---|---|---|
|  `visible` |`void` | get the visible model records  | `Label::visible()->get()` |
|  `hidden` |`void` | get the hidden model records  | `Category::visible()->get()` |

## Handling File Upload
This package doesn't come with file upload feature (yet) Instead you can use [laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) by __Spatie__,
to handle file functionality.

The steps are pretty straight forward, all what you need to do is the following.

Extends the `Ticket` model, by creating a new model file in your application by
```
php artisan make:model Ticket
```

Then extend the base `Ticket Model`, then use `InteractWithMedia` trait by spatie package, and the interface `HasMedia`:

```php
namespace App\Models\Ticket;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends \Coderflex\LaravelTicket\Models\Ticket implements HasMedia
{
    use InteractsWithMedia;
}
```

The rest of the implementation, head to [the docs](https://spatie.be/docs/laravel-medialibrary/v10/introduction) of spatie package to know more.

## Enterprise service desk

Open a ticket through `TicketDesk`. Priority is scored from impact and urgency unless you pass one yourself. The row receives a uuid and a number such as `INC-000001`, `REQ-000001`, `PRB-000001`, or `CHG-000001`.

```php
use Coderflex\LaravelTicket\Desk\TicketDesk;

$ticket = app(TicketDesk::class)->open([
    'title' => 'VPN is down',
    'message' => 'The remote gateway stopped answering.',
    'type' => 'incident', // incident, service_request, problem, change
    'impact' => 'high',   // low, medium, high, critical
    'urgency' => 'high',
    'channel' => 'portal',
    'organization_id' => $organization->id,
    'assignment_group_id' => $group->id,
    'service_id' => $service->id,
], $requestUser);
```

Move the ticket with the workflow methods. Illegal moves throw `InvalidTicketTransition`. A change cannot enter `in_progress` until someone approves it (`ApprovalRequired`). Resolving or closing throws `MissingCustomFields` when a required custom field is empty.

```php
$ticket->assignTo($agent);
$ticket->assignGroup($networkGroup);
$ticket->start();                 // in_progress
$ticket->pend('customer');        // pauses the SLA
$ticket->resolve($resolutionCode); // or close(), cancel(), archive()
$ticket->requestApproval($manager)->approve('Window approved.');
```

Status is one of `new`, `open`, `in_progress`, `pending`, `resolved`, `closed`, `cancelled`, `archived`. `isClosed()` is true only for `closed`. Pending reasons are `customer`, `vendor`, `change`, and `other`.

Public replies and internal notes are different records. A public reply from someone other than the requester stamps the first-response clock. Notes do not. Locked, archived, and cancelled tickets reject public replies; the assignee and any user that implements `CanManageTickets` (or the `laravel_ticket.agents.callback` config) can still reply to a locked ticket.

```php
$ticket->messageAsUser($agent, 'We failed the link over.');
$ticket->note('Carrier ticket is ETTR 16:00.', $agent);
```

Link related work, record time, and attach a file:

```php
$problem->addChild($incident);
$incident->markDuplicateOf($canonical);
$incident->logWork(30, 'Rebooted the concentrator.', $agent);
$incident->attachFile($request->file('log'), $agent);
$incident->setCustom('hostname', 'vpn-edge-01');
```

Seeded SLA policies cover critical (15m / 4h), high (1h / 8h), normal and medium (4h / 24h), and low (8h / 48h). A policy with a matching ticket type wins over the priority default. Policies can count minutes on the business calendar in `config/laravel_ticket.php`. Entering `pending` pauses the clock and pushes both due dates when work resumes.

Queues and reporting:

```php
Ticket::query()->visibleTo($user)->active()->get();
$report = app(\Coderflex\LaravelTicket\Reporting\ServiceDeskReport::class);
$report->backlog();
$report->slaAttainment();
$report->meanTimeToResolveMinutes();
```

`TicketPolicy` is registered for the ticket model. Requesters, affected users, watchers, assignees, and assignment-group members can view. Agents, assignees, and group members can update. Only agents can delete. Implement `Coderflex\LaravelTicket\Contracts\CanManageTickets` on the user model, or return true from `laravel_ticket.agents.callback`, to mark an agent.

Every status and assignment change is written to `ticket_activities`. Categories can be nested with `parent_id`. Services can point at an assignment group, and configuration items attach to both a service and a ticket.

If you already published the original migrations, publish and run `install_enterprise_itsm` as well. It widens `tickets.message` to text and adds the service-desk tables.

## Shared mailboxes

A mailbox is one Exchange Online address. Several assignment groups can connect to it. Exactly one of those groups is primary. Mail that does not match an existing ticket opens on the primary group. A reply stays on the ticket it belongs to, even after that ticket moves to another group that shares the address.

Inbound mail uses Microsoft Graph application permissions (client credentials), not a user's password. The app registration needs `Mail.Read`. If the tenant limits mailbox access, add an application access policy for each address. Credentials can live on the mailbox or in `laravel_ticket.mail.graph`, which reads `TICKET_GRAPH_TENANT_ID`, `TICKET_GRAPH_CLIENT_ID`, and `TICKET_GRAPH_CLIENT_SECRET`.

Outbound mail does not go through Exchange Online. Replies use the Laravel mailer (`MAIL_MAILER=smtp` for a relay). The From address is the shared mailbox, for every group connected to it. Internal notes are not sent. A message from the requester is not sent back to them.

```php
use Coderflex\LaravelTicket\Models\Mailbox;

$mailbox = Mailbox::query()->create([
    'name' => 'Help Desk',
    'address' => 'help@contoso.com',
    'tenant_id' => config('laravel_ticket.mail.graph.tenant_id'),
    'client_id' => config('laravel_ticket.mail.graph.client_id'),
    'client_secret' => config('laravel_ticket.mail.graph.client_secret'),
]);

$network->connectMailbox($mailbox, true); // primary: new mail opens here
$desktop->connectMailbox($mailbox);       // replies still land on the right ticket
```

`tickets:fetch-mail` runs every minute when `laravel_ticket.mail.schedule` is true. Unknown senders need `laravel_ticket.mail.system_user_id`, or a `resolve_sender` callback that returns a user id. The customer address is stored on `requester_email` and `sender_email` either way.

Threading matches `In-Reply-To` and `References` against the mailbox ledger, then a ticket number in the subject such as `[INC-000001]`. The same Graph message is stored once.

Publish and run `add_support_mailboxes` after the enterprise migration.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [ousid](https://github.com/ousid)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
