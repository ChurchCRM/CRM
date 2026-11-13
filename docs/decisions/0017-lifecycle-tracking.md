# RFC: Member lifecycle tracking

> **BLUF**
> ChurchCRM has no first-class lifecycle records today. Baptisms, marriages, funerals and similar milestones live in free-text notes or generic person custom fields — places that cannot act as a church's register and cannot be reported across people or time. Two long-standing asks carry this: #3175 (track member lifecycle events — baptisms, marriages, funerals — with reporting, filed 2017, with a maintainer co-designed schema in its thread) and #5324 (sacramental register fields on a person, whose thread concluded that custom fields are insufficient context). This RFC proposes lifecycle tracking as a designed capability: **typed lifecycle event records on the person, with register views and reporting** — and leaves its home, core feature or core plugin, as an explicit maintainer decision.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-06
**Owner:** @dawoudio
**Proposal issues:** [#3175](https://github.com/ChurchCRM/CRM/issues/3175) (Tracking Church Member Lifecycle Events) · [#5324](https://github.com/ChurchCRM/CRM/issues/5324) (Sacramental register)
**Numbering:** `0017` is provisional. A maintainer assigns the final number on merge.

## Problem

- **Custom fields lack the context a register needs.** A baptism is not a date on a person: it has an officiant, witnesses, a place, and a register entry. A marriage links two people. #5324's thread reached this conclusion directly — generic custom fields cannot carry participants, officiants, witnesses or register semantics.
- **Churches keep formal registers.** Many churches are expected — by their denomination, their own bylaws, or simple pastoral practice — to keep baptism, marriage and funeral registers they can search, print and hand over. Today that register lives outside ChurchCRM, or not at all.
- **Lifecycle events are inherently multi-person.** A marriage joins two people (and may relate to a family record); a baptism involves the person, their family, and an officiant who is often also a person in the system. Single-person fields and notes cannot express that.
- **No cross-person reporting exists.** "Baptisms this year", "wedding anniversaries next month", "funerals conducted" cannot be answered from notes or custom fields — there is nothing countable. (Master confirms: no lifecycle-event tracking or lifecycle reporting exists; "baptism" in code today is only the person's Baptism Date/Location export fields, and there is no sacramental register of any kind.)

## Use cases

1. **Baptism record.** Date, officiant, witnesses, congregation/place and a register entry for an infant or believer's baptism — retrievable years later when the person asks for a certificate or record.
2. **Other milestones.** Dedication, confirmation, ordination and similar events recorded with the same register discipline, under the terminology the church actually uses.
3. **Marriage register entry.** One entry linking two people, with date, officiant and witnesses, visible from both people's records.
4. **Funeral / memorial record.** Date and officiant for a funeral or memorial service, recorded alongside the person's existing deceased handling rather than fighting it.
5. **Register views and printing.** A per-event-type register (all baptisms, all marriages, all funerals) that can be viewed and printed as register pages.
6. **Anniversary and period reporting.** Marriages reaching anniversaries in a period; baptisms, confirmations and funerals counted over a year.

## Goals

- First-class, typed lifecycle event records attached to a person, carrying a date and the participants the event type needs (officiant, witnesses, the other party to a marriage).
- Register views per event type, including printable register output.
- Reporting across people and time (counts and lists per type per period; anniversaries).
- Denominational flexibility: churches practise infant baptism, believer's baptism, dedication, confirmation and ordination in different combinations and under different names; the event set and terminology must bend without code changes.
- Careful interplay with existing person data — deceased flags and dates, family structure — so a lifecycle record never contradicts what the person record already says.

## Non-goals

- **No architecture or data design in this RFC.** Tables, fields and UI placement are decided in the post-approval architecture doc, per the RFC process. (The schema discussion in #3175's thread is input to that doc, not a decision this RFC ratifies.)
- **Not a replacement for general notes or custom fields.** Churches will still note pastoral detail in notes; this RFC covers the formal register, not every mention of an event.
- **No claim of civil or legal certification.** ChurchCRM keeping a register entry does not make it a legal marriage/baptism certificate authority; printed output is the church's own register record.

## Open questions (maintainer decisions)

1. **Core feature or core plugin?** Lifecycle events are near-universal — almost every church baptises, marries and buries — which argues for core, where the register is guaranteed present and integrates with the person record. But practice varies widely by denomination, which argues for a core plugin: opt-in depth, its own terminology and register formats, no burden on churches that do not keep formal registers. This RFC takes no side; the decision shapes everything downstream.
2. **Event-type list: fixed or church-defined?** A shipped list (baptism, dedication, confirmation, marriage, funeral, ordination…) is simpler and reports consistently; a church-defined list bends to every tradition but complicates shared reporting and terminology.
3. **Permissions.** Lifecycle records can be sensitive (a funeral or a marriage is rarely secret, but register detail may be). Who may view and edit them — the same people who can edit the person, or a narrower set?
4. **Marriage and family records.** When a marriage is recorded, should ChurchCRM offer to create or link a family record for the couple, update family roles, or leave family structure entirely manual?
5. **Migration.** Churches already keep this data in custom fields, notes and paper registers. Is a migration/import path from person custom fields in scope for v1, or is manual re-entry acceptable?
6. **Print formats.** What does a "register page" or a baptism/marriage certificate actually need to contain and look like? Whose formats (denominational templates) set the bar?

## Linked issues

Proposal and discussion: #3175 (lifecycle events with reporting; maintainers co-designed a lifecycle-event schema in its thread, Nov 2017, including an officiator-person requirement — never built) and #5324 (sacramental register fields; maintainer approved the feature in its thread and the reporter volunteered, but no delivery followed; its custom-fields-are-insufficient conclusion is a premise of this RFC). Prior-art check (2026-11-06): no open issue specs lifecycle tracking or a sacramental register — these two closed issues are the only specs, so the RFC is drafted on them rather than folding them into a live issue.
