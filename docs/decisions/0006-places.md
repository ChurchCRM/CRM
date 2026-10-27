# RFC: Places

> **BLUF**
> A church keeps each building or address once: a name, an address, and contact details. The list is shared. A person manages it only with the Manage places permission, which is not the same as adding an event. Events are the first feature that will pick from it, and that work is [RFC 0007](0007-event-locations.md). This RFC does not add an event picker, a room, or a calendar. The place list, its API, and its admin pages can ship on their own.

**Status:** Proposed, open for review; no release commitment
**Date:** 2026-10-23
**Owner:** George Dawoud
**Replaces:** the place half of [#5238](https://github.com/ChurchCRM/CRM/issues/5238) and sub-issues #8575, #8576, #8577, #8578, #8579 · **Related:** [RFC 0007](0007-event-locations.md), closed [#4223](https://github.com/ChurchCRM/CRM/issues/4223)
**Numbering:** `0006`
Code is cited by symbol on `master` as of 2026-10-23.

## Questions still open

1. Keep the existing `schema.xml` relation to `events_event.location_id`, set `skipSql="true"` on it, and add no SQL foreign key (D4)?
2. Widen the 45-character address, city, and email columns in the same migration (D3)?

## 1. What master has today

The `locations` table and the `Location` model exist. Nothing in the product lets a person add or edit a place.

| What the old epic assumed | Master |
|---|---|
| `locations` is ready to use | `orm/schema.xml` declares `location_id` without `autoIncrement`. `Install.sql` has `AUTO_INCREMENT`. #4223 fixed an older install. Check both before the first insert. `location_timzezone` is misspelled. `location_typeID` is unused. |
| Columns are NOT NULL | `Install.sql` allows NULL for phone, email, and timezone. `schema.xml` does not. |
| `events_event.location_id` is a database foreign key | The `schema.xml` column is `INTEGER NOT NULL DEFAULT 0`, and the table has `<foreign-key foreignTable="locations">` with no `skipSql`. That element generates `Event::getLocation()`, which treats `0` as none. `Install.sql` is `INT DEFAULT NULL`. No install or upgrade script adds a SQL constraint. |

`GET /api/events/{id}/location` already returns the related row or 404. It stays as it is. [RFC 0007](0007-event-locations.md) covers what events, the portal, and volunteer screens do with a place.

## 2. Goals and non-goals

**Goals**

1. A person with Manage places adds, edits, deactivates, and deletes an unused place. Adding an event does not grant that.
2. The list is not owned by events. Another feature stores `location_id` and uses the same service.
3. Each place says whether members may see its street address.

**Non-goals:** an event picker, a room, a series, an "Online" label, calendar feeds, embedded maps, attendance by place, a saved list of rooms, `location_typeID` tags, kiosk routing, room booking, and copying a family's address into this list. A family's address stays on the family until someone saves it here on purpose.

## 3. Decisions

| # | Decision |
|---|---|
| D1 | **A flat list.** Sort by how many records point at the place, then by name. There is no "main" flag. Today the only stored pointer is `events_event.location_id`. |
| D2 | **Soft delete.** New column `is_active` (default 1). An inactive place leaves the pickers. Records that already point at it keep showing it. |
| D3 | **One migration** `src/mysql/upgrade/<version>-places.sql` and a matching `orm/schema.xml` change: add `is_active`; make `location_id` auto-increment in both; let phone, email, and timezone be NULL in both. Optionally widen `location_address`, `location_city`, and `location_email` to 255 (question 2). The `location_timzezone` typo stays. |
| D4 | **No new SQL foreign key.** `0` means "no place" on an event, and a constraint would reject those rows. Keep the `<foreign-key foreignTable="locations">` element and set `skipSql="true"`, the same way the person contacts are mapped, so `Event::getLocation()` stays (question 1). |
| D5 | **Delete is blocked while anything points at the place.** The service counts every stored `location_id` and answers 409: "Used by N records. Deactivate it instead, or reassign them first." A feature that starts storing `location_id` adds its table to that count in the same change. `DELETE` never changes `is_active`. There is no `?force`. |
| D6 | **`LocationService` is the only writer.** It lives in `src/ChurchCRM/Service/LocationService.php`. Routes stay thin. It does not know about event types, rooms, or calendars. It counts other tables only for D1 and D5. |
| D7 | **Each place decides whether members see the street address.** New column, default shown. The form says that next to the address, in plain language. Staff who can edit the place always see the address. Who else sees it is the caller's rule. [RFC 0007](0007-event-locations.md) sets that rule for events. |
| D8 | **Manage places is its own permission.** New `userconfig` security flag `bManagePlaces`, default FALSE, labeled "Manage places", on the same permissions card as `bAddEvent`. `User::isManagePlacesEnabled()` is true for an admin or for that flag, and false for an EditSelf-only user, matching `isAddEventEnabled()`. Add Event does not turn it on, and it does not turn Add Event on. Existing non-admin users do not receive it in the upgrade. The menu, the admin pages, and every write use `ManagePlacesRoleAuthMiddleware`. A signed-in user without it gets 403. When [RFC 0002](0002-rbac-redesign.md) lands, this flag becomes the `places.manage` grant. Listing active places for a picker is not this permission. [RFC 0007](0007-event-locations.md) allows an event editor to list them. That list omits a street address the place hides when the caller cannot manage places. |
| D9 | **Pages and API are not under events.** API: `/api/locations` in `src/api/routes/locations.php`. Pages: `/locations`, `/locations/new`, `/locations/{id}` in `src/locations/`. Menu label: "Places", its own item, shown only when the user can manage places. It is not inside the Event Types admin block. |

## 4. Surface

| Route | Guard | Does |
|---|---|---|
| `GET /api/locations` | Manage places | List. `?active=1` limits to active places. Wrapper `{Locations: [...]}`, sorted by use count then name. |
| `GET /api/locations/{id}` | Manage places | One place, including the street address. 404 when missing. |
| `POST /api/locations` | Manage places | Create. Name is required. Address may be empty. |
| `PUT /api/locations/{id}` | Manage places | Update, including `is_active` and the address flag. |
| `DELETE /api/locations/{id}` | Manage places | Delete an unused place (D5). 204 on success. |

A list item carries `Id`, `Name`, `Address`, `City`, `State`, `Zip`, `Country`, `Phone`, `Email`, `Timezone`, `IsActive`, `ShowAddress`, `UseCount`, and `LastUsedAt`. Unauthenticated is 401. A signed-in user without Manage places is 403, including a user who only has Add Event. Invalid input is 400.

The list page columns are name, address, city, phone, last used, use count, and an Active/Inactive badge, with Edit, Deactivate/Activate, and Delete. The form fields are name, address, city, state, zip, country, phone, email, timezone (`DateTimeZone::listIdentifiers()`), the address flag, and active. Email format and phone length are checked on the server.

## 5. Delivery

Two PRs, in order. Neither one changes an event screen.

| PR | Contents | Tests |
|---|---|---|
| 1. Data + API | D3 migration, `bManagePlaces`, `schema.xml`, `LocationService`, five routes, OpenAPI | Cypress API spec: create, read, update, 404, 409 on delete, 403 for Add Event without Manage places |
| 2. Pages | List, new, edit, "Places" menu entry, permissions-card checkbox | Cypress UI spec: the menu is absent without the permission. Playwright capture spec in the same PR. |

UI follows `frontend-development.md` and attaches screenshots. Strings use `gettext()` / `i18next.t()`. One docs tracking issue for the user-visible pages.

## 6. Risks

- **Privacy:** a place can be a home. The address is shown until someone hides it (D7). Only someone with Manage places can save a place (D8).
- **Orphans:** with no SQL foreign key (D4), a direct database delete can leave a `location_id` pointing at nothing. `getLocation()` already returns null for a missing row.
- **Schema drift:** `Install.sql` and `schema.xml` disagree today. PR 1 makes them match.

## 7. Old issues

Close these when this RFC is accepted. They stay open until then.

| Issue | Outcome |
|---|---|
| #8575 `is_active` | Closed. D2, D3. PR 1 |
| #8576 read API | Closed. §4. PR 1 |
| #8577 write API | Closed. §4, D5. PR 1 |
| #8578 list page | Closed. §4. PR 2 |
| #8579 new and edit forms | Closed. §4. PR 2 |

#5238 and #8580 to #8583 stay open for [RFC 0007](0007-event-locations.md). File the two implementation issues when this RFC is accepted.
