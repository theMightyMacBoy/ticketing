# Changelog

All notable changes to `laravel-ticket` will be documented in this file.

## Unreleased

- Add an admin guide and a user guide under `docs/`.
- Sync a single sign-on user's manager from the ID token or from Microsoft Graph. The manager gets two queues, "Tickets opened by my team" and "Open tickets by my team", and can open those tickets, including confidential ones.
- A support group can block the requester's manager from those team queues. Tickets on that group stay hidden from the manager unless the manager is also working the ticket. The demo HR group uses the block.
- Restyle the desk, portal, and sign-in screens as one console: a fixed navigation rail, a dense queue, and a ticket page with the conversation separate from assignment and status.
- Add email-first sign-in for requesters and technicians. The address is matched to an OpenID Connect provider or to a local password. A directory override can keep a local account on a company domain. The first successful single sign-on creates the account. ID tokens must be RS256 and must name the same email the person typed.
- Add an agent desk and requester portal: queue search, public replies, internal notes, assignment, transfer, approval, catalog requests, setup, a staff directory, and requester export and erasure. Confidential tickets are hidden from platform admins who are not involved. Opening a ticket, assignment, approval, and SLA breach can be emailed. A failed mailbox fetch records `last_error` and can page `TICKET_MAIL_ALERT_TO`. Graph fetch follows extra pages and retries when the unread filter is rejected. `tickets:seed-demo` loads a demo IT, HR, and Facilities desk. Set `TICKET_DEMO_SEED=true` to insert that organization during migration.
- Add an IT service-desk domain: ticket types and public numbers, impact × urgency priority, status workflow, SLA clocks with a business calendar, assignment groups, organizations, approvals, internal notes, links, work logs, attachments, a service catalog, custom fields, audit history, queue visibility, and service-desk reporting.
- Add shared Exchange Online mailboxes. Graph application auth fetches unread mail. New mail opens on the mailbox's primary support group, and replies stay on the parent ticket. Outbound replies go through the app's SMTP mailer as the shared address.
- Optional per-mailbox retention deletes read inbox mail older than a set number of days. `tickets:purge-mail` runs at 00:00 UTC. Unread mail is kept.
- Add departments for a shared service desk. Each department has its own agents, ticket types and numbers, catalog forms, business calendar, approval chains, and handoff into another department's queue. Reports can be scoped with `forDepartment()`.
- `assignTo()` stores a user id. `isClosed()` is true only when the status is `closed`. `markAsResolved()` moves the ticket to `resolved`.
- New tickets opened through Eloquent default to status `new`. `tickets.message` is text. Ticket and message models use `$fillable`.

## v1.1.0 - 2022-10-22

### What's Changed

- Fix typo by @akiyamaSM in https://github.com/coderflexx/laravel-ticket/pull/1
- Bump dependabot/fetch-metadata from 1.3.3 to 1.3.4 by @dependabot in https://github.com/coderflexx/laravel-ticket/pull/4
- Fix publish migrations by @krekas in https://github.com/coderflexx/laravel-ticket/pull/6

### New Contributors

- @akiyamaSM made their first contribution in https://github.com/coderflexx/laravel-ticket/pull/1
- @dependabot made their first contribution in https://github.com/coderflexx/laravel-ticket/pull/4
- @krekas made their first contribution in https://github.com/coderflexx/laravel-ticket/pull/6

**Full Changelog**: https://github.com/coderflexx/laravel-ticket/compare/v1.0.1...v1.1.0

## v1.0.1 - 2022-09-25

**Full Changelog**: https://github.com/coderflexx/laravel-ticket/compare/v1.0.0...v1.0.1
