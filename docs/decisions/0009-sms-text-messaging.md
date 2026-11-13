# RFC: SMS and text messaging

> **BLUF**
> Churches have asked for text messaging in ChurchCRM for a decade, one use case at a time: texting a group, texting parents, thanking a donor, verifying a family's data, reminding a volunteer. Today exactly one slice exists — group SMS through the core Vonage plugin — while the rest sit as separate unimplemented issues. This RFC proposes treating SMS/text as one messaging capability with a single recipient and consent model, mirroring the revamped email composer: staff pick an audience (group, classification, Sunday School role segment, cart, event non-attendees), see who will receive the message and who is excluded and why, and send through a provider. Event-driven texts (donor thank-you on deposit finalization, data-verification, reminders) plug into the same capability instead of growing one-off hooks. Which provider model to standardize on — Vonage only, or a pluggable contract that can also carry WhatsApp — is the central open question, raised by #8693.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issue:** [#11164](https://github.com/ChurchCRM/CRM/issues/11164) (master tracking issue) · **Related:** [#2545](https://github.com/ChurchCRM/CRM/issues/2545), [#2544](https://github.com/ChurchCRM/CRM/issues/2544), [#2546](https://github.com/ChurchCRM/CRM/issues/2546), [#2547](https://github.com/ChurchCRM/CRM/issues/2547), [#8693](https://github.com/ChurchCRM/CRM/issues/8693), [#9638](https://github.com/ChurchCRM/CRM/issues/9638), [#375](https://github.com/ChurchCRM/CRM/issues/375), [#98](https://github.com/ChurchCRM/CRM/issues/98)
**Numbering:** `0009` is provisional. A maintainer assigns the final number on merge.

## Summary

- **One capability, not five features.** Every SMS ask in the tracker is the same shape — choose an audience, respect consent, send through a provider, keep a record. Build that shape once.
- **Mirror the email composer.** The 7.8 email revamp already solved recipient resolution, role inclusion, previews with exclusion reasons, and send limits. Text messaging should present the same model with SMS rules (mobile number present, not opted out) instead of inventing a second one.
- **Audiences that exist today stay.** Groups and Sunday School role segments (teachers / parents / kids) already work via the Vonage plugin and the people text tools; classifications (#2544) are the notable missing audience.
- **Event-driven messages are part of the same capability.** Donor thank-you texts when a deposit is finalized (#2545), SMS/voice data verification (#2546), volunteer reminders (#9151, #9710) and auto-response signup texts (#2547) should be templates on the messaging capability, driven by the timer-job / event infrastructure, not separate subsystems.
- **Consent is a first-class rule.** A person who opted out of texts (the existing do-not-text property) is never sent to, and the composer shows exclusions before sending, as email does.
- **A record of every send.** Sent texts land on the person/family timeline like emails do, so staff can answer "did we text them?" without leaving ChurchCRM.

## Use cases

1. **Ministry communication.** A Sunday School coordinator texts the parents of one class about a room change (audience: class → parents segment). A group leader texts group members about a cancellation (audience: group).
2. **Targeted outreach.** An admin texts everyone in a classification — for example all members, or all first-time guests from last month (audience: classification, #2544).
3. **Donor stewardship.** When a deposit is finalized, each donor in it with a mobile number gets a short thank-you text (#2545).
4. **Data verification.** The family verification flow offers SMS (or voice) delivery of the verification link/code, not only email (#2546).
5. **Volunteer operations.** Shift reminders and schedule notifications by text, on the preferences model the volunteer work already sketches (#9151, #9710).
6. **Two-way basics.** A keyword/auto-response for signups (#2547) — listed as a use case, but explicitly a later phase (see Non-goals).

## Goals

- A church can send a text to any audience it can email today, plus classifications, from one composer with one consent and preview model.
- Every automated text (thank-you, verification, reminder) is a template on the same capability, visible and editable by staff, with sends recorded.
- Churches are not locked to one provider's pricing or coverage; adding a provider is configuration, not a rewrite (the size of this goal is the §Open questions decision).
- Members experience one consistent opt-out: opting out of texts stops all non-essential texts everywhere.

## Non-goals

- **Not a marketing platform.** No campaigns, drip sequences, or analytics funnels; newsletters stay with RFC 0008's providers.
- **No two-way inbox in v1.** Reading and replying to texts inside ChurchCRM (beyond the auto-response case) is out of scope for the first slice.
- **No architecture in this RFC.** Provider contracts, queueing, and data model are decided in the post-approval architecture doc, per the RFC process.

## Open questions (maintainer decisions)

1. **Provider model.** Vonage-only (deepen the shipped plugin), or a pluggable provider contract — and if pluggable, is WhatsApp (#8693, via Symfony Notifier) a first-class channel or a later provider?
2. **Core vs plugin.** Does the composer/audience capability live in core with providers as plugins (the RFC 0008 newsletter shape), or does all of SMS stay a plugin concern?
3. **Consent scope.** Is the existing per-person do-not-text property sufficient, or do automated texts (thank-yous, reminders) need their own opt-in categories separate from staff-composed texts?
4. **Cost visibility.** Should the composer estimate message cost/segments before sending, and should sends be capped like email's 500-recipient limit?
5. **Verification channel priority.** For #2546, is SMS verification in the first slice, or does it wait until the Member Portal verification work settles?

## Linked issues

Master list with current state: #11164. This RFC, once decided, sets the disposition for #2544, #2545, #2546, #2547 and #8693; the historical asks (#98, #375, #2149, #9151, #9574, #9710) are context, already closed.
