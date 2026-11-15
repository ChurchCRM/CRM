# RFC: Typed annual dates

> **BLUF**
> Adding a recurring date to ChurchCRM today means a schema change plus new code on every surface that shows it: calendar, dashboard, kiosk, portal, reports. This RFC proposes a **date-type registry and per-person / per-family date rows** (month, day, optional year). A church or a feature adds a date type as data, and the shared annual-date rules from #11449 (configured-timezone today, February 29 observance, windows) apply to it automatically. Birth and death stay on the person row because age gates and deceased filtering depend on them; they are listed in the registry so their calendar and visibility rules are configured the same way. Lifecycle register records ([RFC 0017](0017-lifecycle-tracking.md)) point at a date row instead of storing a second copy of the date.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-15
**Owner:** @dawoudio
**Related:** [Epic #11449](https://github.com/ChurchCRM/CRM/issues/11449) (annual-date centralization) · [RFC 0017](0017-lifecycle-tracking.md) (lifecycle tracking)
**Numbering:** `0021` is provisional. A maintainer assigns the final number on merge.

## Problem

- **Storage is split by accident.** Birthdays are three columns (`per_BirthMonth`, `per_BirthDay`, optional `per_BirthYear`). Wedding, membership and friend dates are single DATE columns. ORM selection by month and day therefore works for birthdays but not the others. #11449 slice 1 has to use `LIKE '%-MM-DD'` on `fam_WeddingDate` to stay free of SQL date functions.
- **Each new date type costs a schema change and code on every surface.** The Birthdays and Anniversaries calendars are near-clones. #11449 adds a third (Death anniversaries) by hand. Baptism or confirmation anniversaries would each need another.
- **Custom-field dates do nothing.** A person custom field of type Date stores a value, but it never appears on a calendar, a dashboard window or the kiosk, and it ignores the February 29 setting.
- **Lifecycle records will need dates.** RFC 0017 proposes baptism, marriage and funeral records. If each brings its own date column, the family wedding date and the marriage record become two sources of truth for one fact, which RFC 0017 already flags (its questions 4 and 7).

## Goals

- A new recurring date is a row in a registry, not a migration.
- Every date type gets the same behaviour from #11449's shared core: observed day, signed days until, windows, "turning N" or years-since labels.
- Selection stays ORM-only and indexable: plain equality on month and day, with the February 29 mapping in one query helper.
- Per-type configuration replaces per-type code: calendar on or off, Member Portal visibility, dashboard and kiosk inclusion.
- One date per fact: lifecycle records and future features reference a date row.

## Non-goals

- Moving birth or death dates off `person_per`.
- Moving date deactivated or date entered. They are status and audit, not annual dates.
- Leadership terms ([RFC 0019](0019-leadership-history.md)). A term is a period with a start and an end, not an observance.
- Register detail (officiant, witnesses, place). That stays in RFC 0017's records.
- New permissions. Editing a person's or family's dates follows the existing edit rights.

## Proposal

### Schema sketch

```
date_type_dty                     -- the registry
  dty_ID
  dty_Key            unique slug: wedding, membership, friend, baptism, ...
  dty_Label          shown in editors and calendars; translatable for system types
  dty_AppliesTo      'person' | 'family'
  dty_Source         'row' | 'person_birth' | 'person_deceased'
  dty_IsSystem       seeded types cannot be deleted or re-keyed
  dty_AllowMultiple  default 0
  dty_ShowOnCalendar
  dty_PortalVisibility  'staff' | 'members'
  dty_InDashboard, dty_InKiosk
  dty_Order, dty_Active

person_date_pdt                   family_date_fdt (same shape, fam_ID)
  pdt_ID
  pdt_PerID          FK person_per, cascade delete
  pdt_DtyID          FK date_type_dty
  pdt_Month          TINYINT, required
  pdt_Day            TINYINT, required
  pdt_Year           SMALLINT, optional
  pdt_Note           short text, optional
  INDEX  (pdt_DtyID, pdt_Month, pdt_Day)
  INDEX  (pdt_PerID, pdt_DtyID)
```

- **Month, day and optional year, no DATE column.** This matches how birthdays are already stored, allows a date without a year, and makes "observed on" an equality filter that can use the index.
- **Two owner tables, one registry.** Real foreign keys and cascade deletes, which a single polymorphic owner column cannot give.
- **`dty_Source`** lets birth and death take part in the registry without moving their data. Their calendar, portal visibility and window flags live on the type row like every other type, while the values stay on `person_per`.
- **No JSON metadata column.** `pdt_Note` covers today's needs. Structured detail belongs to the feature that owns it (RFC 0017).
- **Uniqueness on single-valued types** is enforced in the model, since whether a type allows several rows lives on the type row, not the date row.

### Seeded system types

| Key | Applies to | Source | Calendar | Portal | Notes |
|---|---|---|---|---|---|
| `birth` | person | `person_birth` | on | members | Portal directory privacy modes still decide what a member sees |
| `death` | person | `person_deceased` | on | staff | #11449 Decision 6 becomes configuration |
| `wedding` | family | row | on | members | Migrated from `fam_WeddingDate` |
| `membership` | person | row | off | staff | Migrated from `per_MembershipDate` |
| `friend` | person | row | off | staff | Migrated from `per_FriendDate` |

Churches add their own types (baptism, confirmation, dedication) from an admin list editor. Shipping none of them by default keeps the registry neutral across traditions, the same concern RFC 0017 raises in its question 2.

### Behaviour and code

- The `AnnualDate` class from #11449 slice 1 is unchanged. A date row maps directly onto `new AnnualDate($month, $day, $year)`.
- Queries: `PersonQuery::filterByDateObservedOn(string $typeKey, $date)` and a window variant, joining `person_date_pdt`. They use the same `AnnualDate::recordedMonthDaysObservedOn()` mapping as the birthday helpers, so the February 29 rule stays in one place.
- Calendars: one system calendar class parameterised by date type replaces the per-type clones. `dty_ShowOnCalendar` and `dty_PortalVisibility` decide where it appears. The portal calendar service filters by visibility, so a staff-only type cannot reach member feeds by omission.
- Model methods from #11449 (`getWeddingAnniversaryOccurrence()` and the rest) keep their names and read through the date row. Call sites do not change.

### Relationship to RFC 0017

- A lifecycle record that has an annual date stores a reference (`pdt_ID` or `fdt_ID`) rather than its own date. Recording a baptism creates or links the person's `baptism` date row.
- **Marriage:** the family's `wedding` row is the single source of truth. A marriage record references it. This is the recommended answer to RFC 0017's question 4.
- **Funeral:** the person's date deceased stays authoritative. A funeral record has its own service date (a funeral is not the death) and does not set or override date deceased.
- If RFC 0017 lands as a plugin, it registers its date types in this registry rather than adding its own calendar.

### Custom fields

Rule: if a date should recur, appear on a calendar or be windowed, it is a date type. Otherwise it is a custom field. A one-time "convert this custom date field into a date type" admin action is a possible v1 add-on (open question 3).

## Migration

1. **Now:** #11449 slices 1–5 proceed on the current columns. They target model methods, so the storage swap later touches only getters and query helpers.
2. **Phase 1:** add the tables and seed the system types. An upgrade script backfills wedding, membership and friend rows from the old columns (a zero or partial date is skipped and logged). Model getters and setters read and write the date rows. The old columns stop being written.
3. **Phase 2 (next release):** drop `fam_WeddingDate`, `per_MembershipDate` and `per_FriendDate` after a grep shows no reader. CSV import and export, the API and reports move to the model methods in phase 1.
4. **Phase 3:** the admin date-type editor, church-defined types, and the generic calendar. Each can ship separately.

## Costs and risks

- One upgrade script with a backfill, and a release where both shapes exist in the schema.
- Wedding, membership and friend lookups become a join. This is acceptable because none of them sit on a sign-in or age-gate path.
- The portal directory privacy modes are keyed to birthdays and anniversaries today, so they need a per-type mapping.
- Every editor, import, export and API path that reads the three migrated columns must move in phase 1. A missed reader silently returns empty dates, so phase 2 waits for the grep.

## Open questions (maintainer decisions)

1. **Should the registry list birth and death through `dty_Source`, or leave them out entirely?** Listing them gives uniform configuration. Leaving them out keeps the registry to stored rows only.
2. **Are church-defined types in v1, or only the seeded system types?** System types alone already fix storage and the calendar duplication.
3. **Is the custom-field-to-date-type conversion in scope for v1?**
4. **How do portal directory privacy modes extend to new types?** Options: per type (hidden / without year / with year), or a single mode for all non-birthday types.
5. **Do church-defined types reach the dashboard and kiosk by default, or only when an admin opts them in?** The windows from #11449 apply either way.
6. **Should this RFC and RFC 0017 be decided together?** The marriage source of truth is shared between them.
