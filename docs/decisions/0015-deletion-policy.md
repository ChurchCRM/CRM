# RFC: Deletion policy — safeguards, data-integrity checks, and admin force-delete

> **BLUF**
> Deleting records in ChurchCRM is inconsistent today. Some deletes refuse when related data exists, others silently cascade and destroy records the user never saw, most leave no audit trace of what happened, and there is no admin override anywhere — every guard is absolute, so a legitimate cleanup can simply be impossible. An inventory of every delete action in the current code (people, families, groups, events, calendars, finance, users, notes, fundraisers, kiosks, properties, volunteers) confirms the spread: gating is entirely the legacy per-user permission flags, guard posture is inverted between sibling features, and bulk delete barely exists. This RFC proposes **one deletion policy for every main record type**: a known delete-effects map per entity, one default safeguard path (impact summary, data-integrity check, explicit confirmation), and an admin force-delete override that is always audit-trailed.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-06
**Owner:** @dawoudio
**Proposal issues:** [#3761](https://github.com/ChurchCRM/CRM/issues/3761) (bulk people delete) · [#8659](https://github.com/ChurchCRM/CRM/issues/8659) (bulk event delete) · [#8794](https://github.com/ChurchCRM/CRM/issues/8794) (recurring-series delete — follows this policy) · [#8672](https://github.com/ChurchCRM/CRM/issues/8672) (audit-trail shape on user delete) · [#10485](https://github.com/ChurchCRM/CRM/issues/10485) (login / last-admin delete guard)
**Numbering:** `0015` is provisional. A maintainer assigns the final number on merge.

## Maintainer direction already recorded

- **All deletes need safeguards and data-integrity checks, and an admin must be able to force-delete.** (Direction during the stale-issue re-triage, 2026-11-06, on #8659 / #3761.)
- **#3761 is already reopened with a safeguard-first framing** — impact summary and explicit confirmation before any bulk people delete. This RFC generalises that framing to the whole delete surface instead of letting each feature invent its own.
- **The #10485 login guard and the #8672 audit-trail decisions stand.** This RFC composes with them; it does not reopen them.

## Where we are today (verified in the current code)

A full inventory of delete actions was taken before drafting this RFC. The findings that motivate a policy:

- **Gating is entirely the legacy per-user flags.** Every delete checked is enforced by the legacy `usr_*` flags via the role middleware family — `DeleteRecords` (people, families, person photos), `ManageGroups` (groups, memberships, roles, kiosks), `Finance` (deposits, payments, funds), `AddEvents` (events, calendars), `ManageFundraisers` (fundraiser), `Notes` plus an author-or-admin rule (notes), admin-only (user accounts, most settings entities). The RFC 0002 RBAC grant layer authorises **zero** deletes today: no delete call site names an RBAC grant. Volunteer v2 is the lone exception pattern — ministry delete goes through its own per-entity authorization service.
- **Guard posture is inverted between siblings.** Family delete *refuses* (403) when the family has pledges or payments, and offers a sanctioned path to move the donations first. Deposit delete does the opposite: it *silently cascades* — deleting a deposit destroys every payment on it, with no guard in the handler. Donation fund delete refuses when pledges reference it; fundraiser delete refuses nothing and cleans up nothing.
- **Some deletes orphan related records.** Deleting a fundraiser leaves its donated items, paddle numbers and multibuy rows behind — those tables declare no foreign keys and the model has no cleanup. Deleting a donated item leaves its multibuy rows pointing at nothing.
- **Some deletes destroy history.** Deleting an event cascade-deletes **all** of its attendance and check-in records — the attendance history is gone with the event, with no archive. (An inactive flag exists as the soft alternative, but nothing steers the user to it.)
- **Audit treatment is all over the map.** Note delete is the gold standard: system/audit timeline entries cannot be deleted through the API at all, and a successful delete writes a `delete-note` audit entry. User delete writes a "system user deleted" timeline note. Person and event deletes fire plugin hooks. Fundraiser, deposit and calendar deletes leave **nothing** — no note, no log entry, no trace of who deleted what.
- **The same concept sits behind different flags.** Deleting a *family* photo needs only EditRecords; deleting a *person* photo needs DeleteRecords. Fundraiser deletion deliberately avoids DeleteRecords ("only for people and families", per the code comment). "Who may delete" is per-module folklore, not a policy.
- **No force-delete exists anywhere.** Every guard found — family finance, group↔event references (409), calendar with events (409), event with people checked in (409), last group role, last list option, login/last-admin (#10485), active ministry — is absolute. An admin who legitimately needs to override one has no product path.
- **Bulk delete barely exists.** The only bulk-capable paths are a family delete that takes its members with it, and the pledge-group delete (all fund-split rows of one payment). Bulk people delete (#3761) and bulk/series event delete (#8659, #8794) have no implementation — they are policy-first work, not just UI work.
- **Cleanup is hand-written PHP almost everywhere.** The core tables declare foreign keys without `onDelete` behaviour (the declared cascades live almost entirely in the Volunteer-v2 and portal tables; fundraiser tables declare no FKs at all), so every delete's blast radius is whatever its `preDelete()` hook, handler or service loop happens to do — which is why coverage varies so much from entity to entity.

## Summary

- **One policy, every record type.** People, families, groups, events, calendars, finance records, users, notes, fundraisers and the rest follow the same deletion rules instead of per-module folklore.
- **Know the blast radius before it runs.** Every delete's effects — what cascades, what refuses, what is orphaned — are mapped per entity and kept current as living documentation.
- **Safeguards are the default path, not an exception.** Impact summary, data-integrity check, explicit confirmation — for single deletes and bulk deletes alike.
- **Admins can force it — on the record.** When the default path refuses and the admin proceeds anyway, that decision is always audit-trailed. There is no silent override.
- **Bulk and series deletes are consumers of this policy**, not one-off implementations (#3761, #8659, #8794).

## Use cases

1. **Cleaning up a duplicate family that gave once.** The family delete refuses because a pledge exists. Today the admin is stuck; under this policy the refusal explains exactly what blocks it, offers the sanctioned move-donations path — and if the admin still proceeds, the force-delete path records who forced it and what was destroyed.
2. **A deposit entered in error.** Deleting the deposit today silently takes every payment on it. Under this policy the impact summary names the payments first, and the delete only proceeds on explicit confirmation of that consequence.
3. **An old event with years of check-ins.** Deleting it today destroys the attendance history. Under this policy the impact summary surfaces the attendance records as the cost of the delete (whether they are archived instead is an open question below).
4. **Closing out a fundraiser.** Deleting the fundraiser today orphans its items and paddle numbers invisibly. Under this policy those related records are part of the mapped effects — cleaned up or explicitly handed somewhere, never silently stranded.
5. **Bulk cleanup after a bad import.** A botched import leaves hundreds of junk people or events. Bulk delete (#3761, #8659) runs the same safeguard path over the whole selection: one impact summary, one integrity check, one confirmation, one audit entry.

## Goals

- **A per-entity delete-effects map, maintained as living documentation.** For every main record type: what the delete cascades to, what it refuses on, what it leaves behind, who may run it. The pre-RFC inventory is the seed; the map is updated whenever a delete's behaviour changes, so the blast radius is known *before* the delete runs.
- **One default safeguard path for every delete:** a pre-delete impact summary (what will be deleted, cascaded, or left behind), a data-integrity check (what references this record and what breaks), and an explicit confirmation step proportionate to that impact.
- **An admin force-delete override** for the cases the default path refuses — always gated to admins, always behind an explicit acknowledgement of what is being overridden.
- **Consistent audit treatment for every delete:** who deleted, what was deleted, when, and whether the default path refused and the delete was forced. No delete leaves nothing.
- **Bulk deletes (#3761, #8659) and series deletes (#8794) are built on this same policy** — the safeguard path, the integrity checks and the audit treatment apply to a selection of records exactly as to one record.

## Non-goals

- **No architecture or implementation design.** Enforcement points, data model and UI plumbing are decided in the post-approval architecture doc, per the RFC process.
- **No soft-delete / restore system decided here.** Whether deletes become recoverable is an open question below, not a premise of this RFC.
- **No change to the #10485 login-guard or #8672 audit-trail decisions themselves.** The login/last-admin guard and the decided audit-trail shape (user account deletable, audit trail kept, a person who has actions is not deletable) are inputs this policy composes with.
- **No re-litigation of individual features' delete requests.** #3761, #8659 and #8794 stay the tracking issues for their features; this RFC settles the policy they build on.

## Open questions (maintainer decisions)

1. **Which authorization system gates deletes going forward?** The legacy per-user flags are the whole story today; the RFC 0002 grant layer gates none of them. Do deletes migrate to grants, stay on flags, or run dual-gated during a transition — and does the inconsistent-flag folklore (family photo vs person photo, fundraiser vs DeleteRecords) get unified either way?
2. **Event attendance: archive or destroy?** Event delete currently destroys all attendance/check-in history. Should attendance be archived (or otherwise preserved) when an event is deleted, and does the same question apply to other history-bearing cascades?
3. **Force-delete scope.** Which refusals may an admin override? Is anything never force-deletable — the last admin (#10485), a family with finance records, an active ministry — or is every refusal overridable with the audit entry as the control?
4. **Soft-delete / trash with restore vs hard delete, per entity.** Which record types, if any, get a recoverable trash instead of immediate hard delete — and for how long?
5. **What must the audit trail retain for forced deletes?** Who and when, certainly — but also the impact summary as presented, the reason given, and the list of cascaded records? Retention period?

## Linked issues

Proposal and discussion: #3761 (bulk people delete — reopened, safeguard-first), #8659 (bulk event delete — folded into this RFC), #8794 (recurring-series delete — follows this policy), #8672 (dangling note authors / audit-trail shape on user delete — decided, composes with this RFC), #10485 (login / last-admin delete guard — an input to this policy). Evidence base: a full delete-surface inventory of the current code (routes, gates, cascades per entity), taken 2026-11-06 ahead of this draft.
