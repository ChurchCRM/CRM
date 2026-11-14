# RFC: Contact sync to external systems and mobile phones

> **BLUF**
> Church contact data lives in ChurchCRM, but people's phones and mail clients are where they actually look each other up. Members want the church directory in their phone's contacts; staff want ChurchCRM people in Google Contacts or Outlook without re-typing. ChurchCRM once shipped an iCard (vCard) capability for exactly this; it is gone — current master has no vCard export at all, let alone live sync. This RFC proposes deciding the sync model for ChurchCRM contacts: which targets (Google Contacts, Microsoft/Outlook, CardDAV, plain vCard for mobile phones), which direction (the #2569 discussion converged on one-way push with ChurchCRM as the single source of truth), whose contacts sync (a member syncing the directory they can see vs staff syncing the full database), and how field mapping stays 1:1 and honest when the target cannot hold ChurchCRM-specific fields.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issue:** [#2569](https://github.com/ChurchCRM/CRM/issues/2569) (Google Contacts API integration, 2017; demand revived June 2024) · **Related:** [RFC 0004](0004-portal-giving-pledges.md) (member portal / self-service accounts), [RFC 0010](0010-organisations-as-contacts.md) (organisations as contacts)
**Numbering:** `0011` is provisional. A maintainer assigns the final number on merge.

## Summary

- **The need is the phone, not the API.** The concrete user story across the #2569 thread: a church member or staff member opens their phone's contacts and the church family is just *there*, current.
- **vCard is the floor.** A standard .vcf export (per person, per family, or a whole-directory file) needs no third-party account, no OAuth, and restores the lost iCard behaviour. Anything live-sync must justify itself beyond this floor.
- **ChurchCRM is the source of truth.** Inbound edits from external systems create conflict and privacy problems; the thread's refined requirement (2024) is one-way push, field sets matched 1:1, ChurchCRM canonical.
- **Permissions define the payload.** A member may only ever receive contacts they are allowed to see in the directory; staff sync is a separate, admin-governed capability. Sync must never become a bulk-export back door around directory privacy settings.
- **Third-party sync has a real tax.** Google and Microsoft contacts APIs mean OAuth apps, verification, token storage and API churn per install or centrally — the RFC decision must own that cost consciously.

## Use cases

1. **Member directory on a phone.** A member exports or subscribes to the church directory (only the people who opted into the directory) and keeps it current in iPhone/Android contacts.
2. **Staff Google Contacts.** Office staff keep ChurchCRM people mirrored into a Google Workspace contacts account so caller ID and Gmail autocomplete work.
3. **New-family welcome.** A member adds the pastor and office contacts to their phone from a vCard link/QR code on the portal, without typing.
4. **One-off sharing.** Staff send a single person's contact card (vCard) from their ChurchCRM profile page, as the old iCard allowed.

## Goals

- Any user can get a standards-compliant vCard for a person/family they may view, and a directory vCard within their directory permissions.
- If live sync is adopted: changes in ChurchCRM propagate to the target on a defined schedule/event, deletions and opt-outs propagate too, and nothing writes back into ChurchCRM.
- Field mapping is explicit and documented per target, including what is dropped when a field has no counterpart.
- Directory privacy is enforced identically for sync, export and on-screen display — one ruleset, three surfaces.
- No contact data flows to a third party without an admin enabling it and a user consenting for their own account.

## Non-goals

- **No two-way sync.** External edits never overwrite ChurchCRM data in this proposal; if that is ever wanted, it is a new RFC.
- **Not a messaging system.** Sync moves contact records; sending is RFC 0009's territory.
- **No calendar/task sync.** Contacts only.
- **No architecture in this RFC.** API clients, CardDAV server design and sync scheduling are decided in the post-approval architecture doc, per the RFC process.

## Open questions (maintainer decisions)

1. **Scope of v1.** vCard export only, or live sync too? If live: which single target first — Google Contacts, Microsoft, or CardDAV (which covers many clients at once)?
2. **Whose sync?** Member self-service (own directory view), staff-wide, or both with separate gates?
3. **Freshness model.** Manual export/subscribe-to-file, periodic push, or change-triggered push — and what staleness is acceptable to show a member?
4. **Opt-in semantics.** Is directory opt-in (existing privacy flags) sufficient consent for sync, or does sync need its own per-person flag?
5. **Hosted burden.** For API targets: per-install OAuth credentials (admin supplies) or a centrally registered ChurchCRM app — who owns verification and quota?

## Linked issues

Proposal and discussion: #2569. Historical note: earlier ChurchCRM/ChurchInfo versions shipped per-person iCard (vCard) downloads; the capability was lost in later rewrites — restoring it is the minimum this RFC asks the project to decide on.
