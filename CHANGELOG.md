# Changelog

All notable changes to `laravel-ticket` will be documented in this file.

## Unreleased

- Add an IT service-desk domain: ticket types and public numbers, impact × urgency priority, status workflow, SLA clocks with a business calendar, assignment groups, organizations, approvals, internal notes, links, work logs, attachments, a service catalog, custom fields, audit history, queue visibility, and service-desk reporting.
- Add shared Exchange Online mailboxes. Graph application auth fetches unread mail. New mail opens on the mailbox's primary support group, and replies stay on the parent ticket. Outbound replies go through the app's SMTP mailer as the shared address.
- Optional per-mailbox retention deletes read inbox mail older than a set number of days. `tickets:purge-mail` runs at 00:00 UTC. Unread mail is kept.
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
