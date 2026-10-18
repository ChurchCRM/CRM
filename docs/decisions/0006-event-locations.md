# RFC: Event locations

> **BLUF**
> Admins define a place once (name, address, contact) and pick it from a list when they create an event. Staff and members then see where an event is. The `locations` table, the `Location` model and `GET /api/events/{id}/location` already exist; the management pages, the picker and the display do not. Ship it as four reviewable PRs. This RFC is meant to replace epic #5238 and its nine sub-issues, which were written before master moved on (7.8.1 today). Those issues are closed with this RFC; their detail is carried into §4 and §8, and new issues are filed when the RFC is ready to be worked on.

**Status:** Draft; open for review, no release commitment  
**Date:** 2026-10-17  
**Owner:** George Dawoud  
**Replaces:** [#5238](https://github.com/ChurchCRM/CRM/issues/5238) (epic) and its sub-issues #8575, #8576, #8577, #8578, #8579, #8580, #8581, #8582, #8583 · **Related:** closed [#3895](https://github.com/ChurchCRM/CRM/issues/3895), [#4223](https://github.com/ChurchCRM/CRM/issues/4223)  
**Numbering:** `0006` is provisional; a maintainer assigns the final number on merge.  
Code is cited by symbol on `master` as of 2026-10-17.

## Questions for the maintainer
1. Four PRs (§5) instead of the nine tickets in the old epic?
2. Keep `events_event.location_id = 0` as "no location" and guard deletes in the service, with no foreign key (D5)?
3. Show the location to portal members and in the iCal feed in this RFC (PR 4), given that a location can be someone's home (D8)?
4. Widen the 45-character address, city and email columns in the same migration (D3)?

## 1. What master has today

The epic's audit was checked against `master`. It holds, with these corrections.

| Epic said | Master |
|---|---|
| `events_event.location_id` is an FK to `locations` | Plain `INTEGER NOT NULL DEFAULT 0` in `orm/schema.xml`. No foreign key. `0` means none. |
| `locations` is ready to use | `orm/schema.xml` declares `location_id` without `autoIncrement`; `Install.sql` has `AUTO_INCREMENT`. #4223 (closed) fixed an older install. Check both before the first insert. Column `location_timzezone` is misspelled. `location_typeID` is unused. |
| `locations` columns are NOT NULL | `Install.sql` allows NULL for phone, email and timezone; `schema.xml` does not. They disagree. |
| Nothing reads a location | The volunteer team page already does: `src/portal/routes/teams.php` passes `$linkedEvent->getLocation()->getLocationName()` to the template. |
| API: only `GET /api/events/{id}/location` | Confirmed (`getEventLocation` in `src/api/routes/calendar/events.php`). It answers 404 when the event has no location. |
| Waits on the event MVC migration (#8550) | Merged. Event pages live in `src/event/routes/` and `src/event/views/`. |
| Menu entry "under Events" | Events menu is built in `Menu.php` (`Events Dashboard` → `event/dashboard`; `Event Types` → `event/types`). |
| No new permission needed | Still true: `AddEventsRoleAuthMiddleware` and `ViewEventsRoleAuthMiddleware` exist. |
| iCal feed shows where | `src/ChurchCRM/dto/iCal.php` and `PortalCalendarFeed` emit no `LOCATION`. |
| Planned for 7.2 / 7.3 | Stale. Target is the next minor after acceptance. |

Confirmed missing: a locations API, management pages, an editor picker, any display on the event dashboard or event view page, an event filter by location, and a menu entry.

## 2. Goals and non-goals

**Goals**
1. An admin manages places once: add, edit, deactivate, delete when unused.
2. An event picks a place from a list instead of free text.
3. Staff see where an event is, with a directions link, on the dashboard and the event page.
4. Members see where, where the church chooses to show it (D8).

**Non-goals** (unchanged from the epic): embedded maps, calendar tooltip badges, attendance reports by place, sub-locations (rooms, halls), `location_typeID` tags, kiosk routing, room booking and double-booking checks.

## 3. Decisions

| # | Decision |
|---|---|
| D1 | **A flat list.** The list sorts by event count, descending, so the main campus rises on its own. There is no "main" flag. |
| D2 | **Soft delete.** New column `is_active` (default 1). Inactive places leave the pickers and keep showing on old events. |
| D3 | **One migration** `src/mysql/upgrade/<version>-event-locations.sql` and a matching `orm/schema.xml` change: add `is_active`; make `location_id` auto-increment in both; let phone, email and timezone be NULL in both. Optionally widen `location_address`, `location_city` and `location_email` to 255 (question 4): 45 characters truncates real addresses and emails. The `location_timzezone` typo stays; renaming it costs a migration for no user benefit. |
| D4 | **Permissions reuse events.** Reads sit behind `ViewEventsRoleAuthMiddleware`; every write route adds `AddEventsRoleAuthMiddleware`. No new permission column. When the RBAC redesign ([RFC 0002](0002-rbac-redesign.md)) lands, these two guards move with the other event routes. |
| D5 | **Delete is blocked while any event uses the place.** The service counts `events_event.location_id` inside the delete and answers 409 with the count: "Used by N events. Deactivate it instead, or reassign the events first." (Sub-issue #8577 instead soft-deleted a referenced place on `DELETE`; the epic's restrict rule wins, so `DELETE` never changes `is_active` and there is no `?force`.) No foreign key: `0` already means "none", and an FK would force a data migration of every existing event for no new behavior (question 2). |
| D6 | **Business logic in `src/ChurchCRM/Service/LocationService.php`**, using Propel (`LocationQuery`, `EventQuery`). Routes stay thin. |
| D7 | **Free text stays possible.** An event with `location_id = 0` is valid. Online events keep using `event_url`; a place needs no address (the address columns accept `''`). |
| D8 | **Members see a place only where the church chooses.** The portal calendar and iCal `LOCATION` show the name and address of an active place. Because a place can be a private home, the location form says "Shown to everyone who can see the event", and the display is a single switch in PR 4 that can ship later or off without touching PRs 1 to 3 (question 3). |

## 4. Surface

**API** (`src/api/routes/calendar/locations.php`, OpenAPI annotated like `events.php`)

| Route | Guard | Does |
|---|---|---|
| `GET /api/locations` | View | List, with `eventCount`; `?active=1` for pickers |
| `GET /api/locations/{id}` | View | One place |
| `POST /api/locations` | Add | Create (name required) |
| `PUT /api/locations/{id}` | Add | Update, including `is_active` |
| `DELETE /api/locations/{id}` | Add | Delete unused place (D5) |

**Pages** (`src/event/routes/locations.php`, `src/event/views/`): `/event/locations` (DataTable, Active/Inactive badge, event count), `/event/locations/new`, `/event/locations/{id}`. Admin page header pattern from `code-standards.md`. Page logic in a `webpack/` entry, not inline script.

**Integration:** a picker on `/event/editor` and `/event/repeat-editor` with an inline "Add new" (active places only; the event's current place is always listed); a badge on `/event/dashboard` rows; a location card on `/event/view/{id}` with a directions link built from the address text; a `location=N` filter beside type and year; the Events menu entry.

**i18n and docs:** strings wrapped with `gettext()` / `i18next.t()`; no `locale:build`. One docs tracking issue for the user-visible feature.

## 5. Delivery: four PRs

Sized by review burden, not by ticket count. Each is independently shippable and mergeable in order.

| PR | Contents | Replaces | Tests |
|---|---|---|---|
| 1. Data + API | D3 migration and `schema.xml`, `LocationService`, the five routes, OpenAPI | #8575, #8576, #8577 | Cypress API spec: CRUD, 404, 409 on delete, 403 without `AddEvents` |
| 2. Management pages | List, new, edit, Events menu entry | #8578, #8579, part of #8583 | Cypress UI spec; Playwright capture spec in the same PR |
| 3. Event integration | Picker (both editors), dashboard badge and filter, event page card | #8580, #8581, #8582 | Cypress specs in `event-editor.spec.js`, `repeat-event-editor.spec.js` and a dashboard filter spec |
| 4. Members | Portal calendar and iCal `LOCATION` (D8), accessibility sweep | the member story in #5238 | Cypress feed spec |

UI PRs follow `frontend-development.md` (Tabler, light and dark, desktop, tablet and phone) and attach screenshots.

## 6. Risks
- **Privacy:** a location can be a private address. Mitigated by D8's wording and its switch, and by staff-only entry (D4).
- **Orphans:** with no FK (D5), a direct database delete can strand `location_id`. `getLocation()` already returns null for a missing row, and every display path treats null as "no location".
- **Schema drift:** `Install.sql` and `schema.xml` disagree today (§1). PR 1 makes them match and its spec fails if they diverge again.

## 7. Disposition of the old issues

| Issue | Outcome |
|---|---|
| #5238 epic | Closed, superseded by this RFC |
| #8575 `is_active` column | Closed → D2, D3; PR 1 |
| #8576 read API | Closed → §4; PR 1 |
| #8577 write API | Closed → §4, D5; PR 1 |
| #8578 list page | Closed → §4; PR 2 |
| #8579 new and edit forms | Closed → §4; PR 2 |
| #8580 picker | Closed → §4; PR 3 |
| #8581 dashboard badge and event card | Closed → §4; PR 3 |
| #8582 filter | Closed → §4; PR 3 |
| #8583 menu, i18n, release notes | Closed → §4, §5; spread across PRs 2 to 4 |

Child issues for PRs 1 to 4 are filed when the RFC is ready to be worked on.

## 8. Detail carried over from the closed issues

- **Migration:** `is_active TINYINT(1) NOT NULL DEFAULT 1`; register the script in `upgrade.json` `current.scripts`; `npm run build:propel` regenerates `Location`, `LocationQuery` and `LocationTableMap`. Verify a fresh install and an upgrade.
- **List API:** wrapper `{Locations: [...]}`, sorted by event count descending then name. Items carry `Id`, `Name`, `Address`, `City`, `State`, `Zip`, `Country`, `Phone`, `Email`, `Timezone`, `IsActive`, `EventCount`, `LastUsedAt` (latest event start). `?active=1` or `?active=0` filters; no parameter returns all. Missing id → 404; unauthenticated → 401; without `isAddEvent` on writes → 403; invalid input → 400; successful `DELETE` → 204. `GET /api/events/{id}/location` is unchanged. Cypress spec: `cypress/e2e/api/private/standard/private.locations.spec.js`.
- **List page columns:** Name, address, city, phone, last used, event count, Active/Inactive badge, action menu (Edit, Deactivate/Activate, Delete with confirm). All/Active/Inactive pill filter as on `types-list.php`; empty state with an Add button.
- **Forms:** Name required; address, city, state, zip, country, phone, email, timezone (`DateTimeZone::listIdentifiers()`); email format and phone length validated server-side; save, activate, deactivate and delete as actions on `/event/locations/{id}`, like `/event/types/{id}`.
- **Picker:** first option "No location" (`0`); an inactive current place is appended labeled "(inactive)"; quick-add modal (Name, Address) posts to `POST /api/locations` and selects the new option.
- **Display:** dashboard badge only when a place is set, eager-loaded with `leftJoinWithLocation()` to avoid N+1; event page card shows name, address, `tel:` and `mailto:` links, an "(Inactive)" label if inactive, and a "Get Directions" link to `https://www.google.com/maps/search/?api=1&query=<urlencoded address>` in a new tab. All output through `InputUtils::escapeHTML()` / `escapeAttribute()`.
- **Filter:** `?location=<id>`; `All` is the default; `none` filters to `location_id = 0`; options ordered by event count then name.
- **Menu:** "Event Locations" under Events, `fa-location-dot`, shown only with `isAddEvent`. Cypress: view-only users do not see it.
- **Polish:** labelled form fields, `aria-label` on icon-only buttons, text beside color-only states, keyboard use of the row menus; release-notes entry; update `routing-architecture.md` with `routes/locations.php`.
