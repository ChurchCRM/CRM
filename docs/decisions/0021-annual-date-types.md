# RFC: Typed annual dates

> **BLUF**
> Adding a recurring date to ChurchCRM today means a schema change plus new code on every surface that shows it. This RFC proposes a **registry of church-defined date types and per-person / per-family date rows** (month, day, optional year). An admin adds a type such as Baptism or Dedication as data. Staff then record those dates inline on the person and family pages, next to the dates the record already holds (birthday, membership, friend, death, wedding), and see them soonest first. The shared annual-date rules from #11449 (configured-timezone today, February 29 observance) apply to every type. Existing date columns stay where they are. Lifecycle register records ([RFC 0017](0017-lifecycle-tracking.md)) point at a date row instead of storing a second copy of the date.

**Status:** Draft for maintainer review; a v1 implementation is in a draft PR for review alongside it
**Date:** 2026-11-15
**Owner:** @dawoudio
**Related:** [Epic #11449](https://github.com/ChurchCRM/CRM/issues/11449) (annual-date centralization) · [RFC 0017](0017-lifecycle-tracking.md) (lifecycle tracking)
**Numbering:** `0021` is provisional. A maintainer assigns the final number on merge.

## Problem

- **Each new date type costs a schema change and code on every surface.** The Birthdays and Anniversaries calendars are near-clones. #11449 adds a third (Death anniversaries) by hand. Baptism or confirmation anniversaries would each need another.
- **Custom-field dates do nothing.** A person custom field of type Date stores a value, but it never appears on a calendar and ignores the February 29 setting. Staff cannot see what is coming up for a person.
- **Staff have no single view of a person's yearly dates.** Birthday sits in contact info, membership and friend dates in the editor, a baptism in a note or custom field.
- **Lifecycle records will need dates.** RFC 0017 proposes baptism, marriage and funeral records. If each brings its own date column, the family wedding date and the marriage record become two sources of truth for one fact (RFC 0017 questions 4 and 7).

## Goals

- A new recurring date is a row in a registry, not a migration.
- Staff record and correct dates where they already work, on the person and family pages, without a separate editor.
- Every date type follows the shared annual-date rules from #11449.
- Per-type configuration instead of per-type code.
- One date per fact: lifecycle records and future features reference a date row.

## Non-goals

- Moving birth, death, wedding, membership or friend dates off `person_per` and `family_fam`. They keep their columns and every existing reader; the staff card shows them read-only beside the church-defined dates, with a link to the editor.
- Leadership terms ([RFC 0019](0019-leadership-history.md)): a period, not an observance.
- Register detail (officiant, witnesses, place): RFC 0017.
- Expiring dates (background-check renewals): a different rule than a yearly observance.
- New permissions (see Access below).

## Proposal

### Schema

```
important_date_type_idt
  idt_ID, idt_Key (unique slug), idt_Name, idt_AppliesTo ('person'|'family'),
  idt_AllowMultiple (default 0), idt_ShowOnCalendar (default 1),
  idt_Active (default 1), idt_Order

person_important_date_pid          family_important_date_fid (same shape, fid_fam_ID)
  pid_ID, pid_per_ID  FK person_per ON DELETE CASCADE,
  pid_idt_ID          FK important_date_type_idt ON DELETE RESTRICT,
  pid_Month, pid_Day  required
  pid_Year            optional
  pid_Note            optional, 255
  pid_DateEntered, pid_EnteredBy, pid_DateLastEdited, pid_EditedBy
  INDEX (per_ID, idt_ID), INDEX (idt_ID, Month, Day)
```

- **Month, day and optional year, no DATE column.** It matches how birthdays are stored, allows a date without a year, and makes "observed on" an equality filter on an index.
- **Two owner tables, one registry.** Real foreign keys: deleting a person removes their dates; a type in use cannot be deleted.
- **One-per-record types** are enforced in the service, since the rule lives on the type row.

### Staff workflow

- **Person and family pages** carry an Important Dates card: every yearly date on the record, soonest first, with the recorded date, "Today / in N days", and the years it marks ("12 years", "turning 41").
- **Add Date** opens inline: type, month, day, optional year, optional note. A one-per-record type already recorded shows as unavailable with "already recorded", so staff edit rather than duplicate. February 29 is accepted without a year or in a leap year.
- **Edit** changes the date and note in place; the type stays. **Remove** asks once.
- **Built-in dates** appear in the same list, read-only, with a link to the person or family editor that owns them.
- **Admins** set up types from People Settings → Important Date Types: suggested types (Baptism, Dedication, Confirmation, First Communion, Spiritual Birthday, Ordination, House Blessing) in one click, or their own by name. Options switch in place. Turning a type off keeps every recorded date and stops new ones; only an unused type can be deleted.

### Access

No new permission. Each surface uses the permission of the data it touches, on the API and the page alike:

| Action | Who | Enforced by |
|---|---|---|
| See a record's important dates, the Upcoming Important Dates page, the staff calendar | Any staff user. Edit Self-only users never reach staff pages | The People app and API entry gates |
| Record, correct or remove a date on a person or family | Edit Records, as for editing the record | `EditRecordsRoleAuthMiddleware` on each write route. The card shows Add, Edit and Remove only to Edit Records |
| Define, rename, switch or delete date types | Administrator (setup) | The admin app and its API |

The Member Portal sees none of it.

### Upcoming Important Dates page

People → Important Dates (`/people/important-dates`) lists church-defined dates observed in the next 7, 30 or 90 days, filterable by type, each linked to its person or family. It answers "whose baptism anniversaries are this month?" without opening each record.

### Calendar

One system calendar, **Important Dates**, places each date of an active, calendar-enabled type on its observed day for living, active people and active families. It implements `StaffOnlySystemCalendar`, which `PortalCalendarService` excludes, so it is never offered to the Member Portal or its feeds.

### Relationship to RFC 0017

- A lifecycle record with an annual date references a date row (`pid_ID` / `fid_ID`) rather than storing its own date. Recording a baptism creates or links the person's Baptism date.
- **Marriage:** the family wedding date stays the source of truth. A marriage record references it (recommended answer to RFC 0017 question 4).
- **Funeral:** date deceased stays authoritative; a funeral record carries its own service date.
- If lifecycle lands as a plugin, it registers its types in this registry rather than adding its own calendar.

### Custom fields

If a date should recur or appear on a calendar, it is a date type. Otherwise it is a custom field.

## Rollout

1. **v1, target 7.9.0:** the tables, the admin setup page, the person and family card, the Upcoming Important Dates page and the staff calendar. A new install ships the suggested types. An upgrade adds the three tables, changes no existing column, and offers the same types as one-click suggestions. Demo data records sample dates.
2. **Later, each on demand:** portal visibility per type (with the directory privacy modes), dashboard and kiosk inclusion (after #11449 slices 2–3), birthday-style greeting emails per type, a "convert this custom date field" action.

## Open questions (maintainer decisions)

1. **Suggested types:** is the shipped list right and neutral enough across traditions?
2. **Portal visibility:** when a type reaches the Member Portal, does it use per-type privacy modes (hidden / without year / with year) like birthdays?
3. **Dashboard and kiosk:** do church-defined types join the birthday and anniversary lists by default, or only when an admin opts a type in?
4. **Custom-field conversion:** worth a one-time admin action in a later release?
5. **Decide with RFC 0017?** The marriage source of truth is shared.
