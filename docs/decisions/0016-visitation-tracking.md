# RFC: Visitation tracking — structured pastoral visit records

> **BLUF**
> Pastoral care in ChurchCRM today leaves no structured trace: a hospital visit, a shut-in call and a bereavement follow-up all live, if anywhere, as free-text notes on a person or family — indistinguishable from any other note, impossible to report on, and with no way to see which follow-ups are still owed. Issue #8980 already specifies the fix in detail: a structured visit record (visitor, person/family visited, date, visit type, notes, follow-up flag), a log-a-visit UI on the person and family views, visits in the person/family history, and a filterable visitation report with export. This RFC is the proposal-level companion to #8980: **structured pastoral visit records with a visibility model designed for sensitive data, follow-up surfacing, and visitation reporting** — settling the product questions around the module so #8980's design-doc/ADR step can settle the data design afterwards.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-06
**Owner:** @dawoudio
**Proposal issue:** [#8980](https://github.com/ChurchCRM/CRM/issues/8980) (Visitation tracking and reporting) · **Related:** [#8690](https://github.com/ChurchCRM/CRM/issues/8690) (original seed ask, folded into #8980) · [#5054](https://github.com/ChurchCRM/CRM/issues/5054) (earlier visitation-reporting ask, superseded by #8980)
**Numbering:** `0016` is provisional. A maintainer assigns the final number on merge.

## Maintainer direction already recorded

- **#8980 is the live home for this work.** It is the maintainer's later, fuller spec (Visit entity or typed Note extension, log-a-visit UI, person/family history, filterable report); the earlier seed ask #8690 is folded into it, and #5054's reporting ask was already superseded by it.
- **RFC before implementation.** Visitation tracking is a new module with sensitive data, so the proposal questions get settled here first; #8980's own "design doc / ADR before implementation" step (new table vs typed Note) follows RFC approval, per the RFC process.

## Summary

- **Visits are records, not notes.** Who visited whom, when, why, and what is still owed are data — a note's free text cannot answer any of them, and pastoral teams currently keep the real answers in notebooks and spreadsheets outside ChurchCRM.
- **Visibility is the sensitive part.** A visit record can carry the most private facts in the whole system — illness, bereavement, family crisis. General note visibility is effectively all-or-nothing for staff; visit records need a model designed for pastoral data from the start, not inherited by default.
- **Follow-up is the workflow.** A visit that surfaces a need — a return visit, a phone call, a referral — creates an obligation. If nothing tracks it, it is silently dropped; due and overdue follow-ups must surface somewhere staff actually look.
- **Reporting closes the loop.** "Which members have not been visited in six months" and "visits per pastor this quarter" are the questions elders and pastors actually ask; they are only answerable if visits are structured data.

## Use cases

1. **Hospital visit.** A pastor visits a member in hospital, logs the visit from the person's record with type "hospital", a short note, and a follow-up for a return visit in three days.
2. **Shut-in / homebound rotation.** An elder team works a list of homebound members; each visit is logged so coverage is visible — nobody visited twice in a week while someone else goes a month without one.
3. **Visit with follow-up.** A home visit after a bereavement creates follow-ups — a call next week, a meal referral — that stay visible until done, not buried in the visit's note text.
4. **Person vs household visits.** A visit is often to a household (a family at home after a new baby) but sometimes to one person (hospital, care home); the record must handle both without duplicating entries per family member.
5. **Reporting.** Visits per pastor or visitor over a period, members not visited within a chosen window, and open/overdue follow-ups — filterable and exportable for elders' meetings.

## Goals

- A structured visit record — **visitor, person and/or family visited, date, visit type, notes, and a follow-up flag** — as specified in #8980, logged from the person and family views and shown in their history.
- **Church-defined visit types** (hospital, home, phone, pastoral care, …), so the type list fits each church's practice rather than a fixed vendor list.
- A **visibility and permission model designed for sensitive pastoral data**: who can see visit records, who can see their note content, and where records may surface are explicit decisions, not defaults inherited from general notes.
- **Follow-up surfacing:** a visit can carry a follow-up with a due date, and due/overdue follow-ups are visible to the people responsible for them.
- **Visitation reporting**, including export: visits by visitor, period, type and subject; unvisited-member views; open follow-ups — the report scope #8980 specifies.

## Non-goals

- **Not a replacement for general notes.** Ordinary person/family notes stay as they are; visit records are a distinct, structured kind of record, and this RFC does not migrate or retire notes.
- **Not a counseling or clinical records system.** Visit records are pastoral-care coordination (who was visited, when, what follow-up is owed), not case notes, counseling documentation or a medical record — that boundary is deliberate and stays.
- **No architecture in this RFC.** The new-table vs typed-Note-extension question is #8980's design-doc/ADR step, taken after this RFC is approved, per the RFC process; API shape, schema and enforcement points live there.
- **No rota or scheduling engine decided here.** Assigning visitors, rotations and reminders are candidate follow-on work; whether they belong in v1 is an open question below, not a goal.

## Open questions (maintainer decisions)

1. **Visibility model.** Do visit records sit behind the RBAC grant system, a pastoral-team scope (a defined set of users/roles), or both? And where may visit records surface — person/family history only, or also dashboards and lists (which would widen who sees that a visit happened, even without note content)?
2. **Visit subject.** Is a visit's subject a person, a family, or either — and when a visit is logged against a family, how does it display on each member's history (shared entry, per-member copies, or head-of-household only)?
3. **Visit types.** Church-defined list options (like other ChurchCRM list fields) or a small fixed set? If church-defined, who may edit the list?
4. **Follow-up mechanics.** Is a follow-up a task in its own right, a reminder/notification, or simply a due date on the visit record that reports and lists can surface? Who gets told when one goes overdue?
5. **Relationship to existing notes.** Do visit records link to ordinary notes (a visit and a note about it side by side), stay fully separate, or should there be a path to convert/migrate note-based visit logs churches already keep?
6. **Retention.** How long should sensitive visit notes be kept? Church-defined retention, indefinite by default, or a review expectation — and does the answer differ for the record (a visit happened) vs its note content?

## Linked issues

Proposal and discussion: #8980 (the full module spec this RFC accompanies). Historical: #8690 (the original seed ask for unified person + family visit history, folded into #8980), #5054 (earlier visitation-reporting ask, superseded by #8980).
