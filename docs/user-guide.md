# User guide

This guide is for people who sign in and use the desk. Setup, mailboxes, and configuration live in the [admin guide](admin-guide.md).

The left rail is the same for everyone who is signed in.

| Link | What it opens |
|---|---|
| Queue | Tickets you are allowed to see. The page title is **Open work**. |
| Catalog | A form that opens a ticket from a published service. |
| My requests | Your own tickets. The page title is **My tickets**. |
| Tickets opened by my team | Every ticket filed by someone who reports to you. |
| Open tickets by my team | The same list, without resolved, closed, cancelled, or archived tickets. |

**Setup**, **Directory**, and **Privacy** appear under Administration only for department agents and platform administrators.

## Sign in

Open `/desk/login` when your organization has turned the sign-in screen on. The host application may use its own login instead. In that case you never see this page.

1. Enter your email address and choose **Continue**.
2. A company address continues at your identity provider. You do not type a password on this site.
3. A local account continues to a password page. The email you typed is kept. **Use a different email** returns to the first page.
4. An address the desk does not know, and that is not on a company sign-in domain, is rejected. The desk does not create an account from an unknown email.

The first time a company sign-in succeeds, the desk creates your account. Being able to sign in does not make you an agent. An administrator grants that in the directory.

**Sign out** is at the bottom of the rail when this sign-in screen is in use.

## Requesters

A requester is the person the ticket is for. You see a ticket when you opened it, when your email is the requester address, or when you are named as the affected person.

### My tickets

**My requests** lists your tickets, newest first, twenty at a time. Each row shows the ticket number, the title, and the status. Choose the number or the title to open it.

You do not see other people's tickets here, including tickets filed by people on your team. Those stay on the team pages, and only for the manager.

### Open a request

**Catalog** lists the services your organization has published. Each service belongs to a department, such as IT, HR, or Facilities.

1. Choose the service.
2. Enter a title and, if you want, details.
3. Fill every required field. The label tells you what the department needs, such as a hostname or a building.
4. Check **Confidential** when the ticket should stay with the people working it. A platform administrator who is not involved cannot open a confidential ticket.
5. Choose **Open ticket**.

The new ticket is numbered for its type, for example `INC-000001` or `CASE-000001`. When mail notifications are on, you receive an email with that number. A reply to that email is added to the same ticket.

### Reply

On your ticket, write in **Reply** and choose **Send reply**. The reply is public. The agent working the ticket can read it. Internal notes written by agents are not shown to you.

You cannot reply when the ticket is archived or cancelled. A locked ticket accepts a reply from the assigned agent and from a platform administrator.

### Email

You can keep the conversation in email.

- A new email to a support address opens a new ticket on that address's primary group.
- A reply is attached to the existing ticket when it answers that thread, or when the subject contains a number such as `INC-000123`, `REQ-000123`, `PRB-000123`, or `CHG-000123`. A department number such as `CASE-000001` is attached when the reply stays on the same email thread.
- Mail you send is not sent back to you.
- Mail from the desk uses the shared support address, through your organization's mail relay.

### Privacy

**Privacy** is linked on the rail for department agents and platform administrators. Any signed-in person can open `/desk/privacy` to export or erase their own tickets.

- **Export** downloads JSON for the email in the form: ticket number, title, body, status, requester, and public replies.
- **Erase tickets** replaces the title with "Erased request", clears the body and requester address, and replaces each reply with `[erased]`. The ticket number stays.

A requester can export and erase their own tickets, including confidential ones. An agent erases only the tickets they are allowed to see. A platform administrator does not erase another department's confidential tickets.

## Agents

An agent works a department or a support group. The directory is where an administrator marks you as an agent. Signing in with a company account does not do that by itself.

### The queue

**Queue** lists every ticket you are allowed to see, newest first, twenty per page. It includes tickets that are already resolved or closed. Use the status word on the row to tell them apart.

You see a ticket when any of these are true:

- You opened it, you are the affected person, or it is assigned to you.
- You watch it.
- You are a member of its support group.
- You are an agent of its department.
- You are the pending approver.
- You are a platform administrator and the ticket is not confidential, or you are involved in that confidential ticket.

Search matches the ticket number, title, requester email, and the opening message. The placeholder says "Number, title, or requester".

A confidential ticket is marked **Confidential**. If you are not involved, it is absent from the queue and the ticket address returns Forbidden.

### The ticket

The ticket page shows the number, title, status, and the conversation.

**Public reply** is sent to the requester when the ticket has a mailbox and notifications can be delivered. The subject includes the ticket number. The reply is not sent when you are the requester, when the destination is the same address as the mailbox, or when the ticket has no mailbox.

**Internal note** stays on the ticket. It is not emailed and it is not shown in the requester's portal. The note is drawn with a warm edge so you can tell it from the public conversation.

**Assign** takes the assignee's email. That person is notified when mail notifications are on.

**Transfer** moves the same ticket to another department or support group. The ticket keeps its number. Choose a department, a support group, or both, then **Transfer**.

**Update status** moves the ticket. The desk refuses a move the workflow does not allow. Resolving or closing also requires every required custom field. Moving into **In progress** waits until required approvals are accepted.

**Approve** and **Reject** appear when an approval is waiting on you. A platform administrator can decide an approval as well. The approver is emailed when the request is created and mail notifications are on.

### Status

| Word on the ticket | Meaning |
|---|---|
| New | Just opened. |
| Open | Accepted into the queue. |
| In progress | Someone is working it. Approval may be required first. |
| Pending | Waiting. The SLA clock pauses. |
| Resolved | The request is done. It can be reopened. |
| Closed | Finished. |
| Cancelled | Stopped. Replies are closed. |
| Archived | Kept for the record. Replies are closed. |

### What an agent does not see

The main queue does not list another person's tickets only because you are their manager. Use the team pages for that, and only the tickets the support group allows a manager to see.

A department agent still sees the department's tickets, including a confidential HR case, from the queue. The manager block does not remove that access.

## Managers

A manager is a person whose email was stored as someone else's manager at sign-in. The relationship is direct: you see tickets for people who report to you, not for people who report to them.

The two team links are always on the rail. Until someone is recorded as reporting to you, both pages say: "No one is recorded as reporting to you. A person's manager is stored when they sign in with single sign-on."

### Tickets opened by my team

This list is every ticket filed by a direct report. Matching uses the requester email, or the email of the account that owns the ticket. Confidential tickets are included and marked **Confidential**.

Search works the same way as the queue.

You can open a ticket from this list. Opening it does not make you the assignee. Public reply, internal note, assign, transfer, and status stay with agents. You can read the ticket.

### Open tickets by my team

This is the same list without tickets that are resolved, closed, cancelled, or archived.

### Tickets a manager cannot see

A support group can block the requester's manager. HR uses this so a case between an employee and HR stays with HR.

When the ticket's current group has that block:

- The ticket is left off both team lists.
- You cannot open it from the team relationship. The address returns Forbidden.
- The empty team page does not say that a hidden ticket exists.

The block follows the group the ticket is on now. A ticket with no support group is still visible to the manager. A ticket that only sits in the HR department, with no group, is still visible to the manager.

If you are also an agent of that department, or the ticket is assigned to you, or you are a member of the group, you still open it from the queue. It stays off the team lists while the block is on.

A platform administrator who is not the manager, and not otherwise involved, still cannot open a confidential ticket.
