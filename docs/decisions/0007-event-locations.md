# RFC: Event places

> **BLUF**
> When someone creates an event, they pick a place from the shared list in [RFC 0006](0006-places.md). Worship at North can start in the Sanctuary while the potluck at North starts in the Hall. Members see the place name. The street address follows the flag on that place. A link stays inside ChurchCRM. This RFC starts after the place list exists. It does not build that list.

**Status:** Proposed, open for review; no release commitment
**Date:** 2026-10-23
**Owner:** George Dawoud
**Depends on:** [RFC 0006](0006-places.md)
**Replaces:** the event half of [#5238](https://github.com/ChurchCRM/CRM/issues/5238) and sub-issues #8580, #8581, #8582, #8583
**Numbering:** `0007`
Code is cited by symbol on `master` as of 2026-10-23.

## Questions still open

None. The church workflow below is decided. The place list's open questions stay in [RFC 0006](0006-places.md).

## 1. What master has today

Events already store `location_id`. There is no picker, no room, and no place on the event dashboard or the event page.

The place name is already shown in four places: portal teams (`src/portal/routes/teams.php`), the volunteer occurrence page (`src/ministries/routes/occurrence.php`), the member calendar (`PortalCalendarService::locationName()`), and volunteer emails (`VolunteerNotificationService::locationNameFor()`). The email path calls `getName()`. `Location` only has `getLocationName()`.

`src/ChurchCRM/dto/iCal.php` emits no `LOCATION`. `PortalCalendarFeed` writes `LOCATION` from the name the portal calendar already sets. The address is not included. `GET /api/events/{id}/location` returns the row or 404, and it stays as it is.

## 2. Goals and non-goals

**Goals**

1. A new event starts from its type. One usual place is filled in with that type's room for that building. Several usual places ask which building first. None leaves the field empty.
2. Staff see the place, the room, and a directions link on the dashboard and the event page.
3. Members see the name and the room. They see the street address only when [RFC 0006](0006-places.md) says that place allows it, with the exceptions in D4.
4. An online event is obvious. A livestream link sits beside the building and is not copied to an external calendar.

**Non-goals:** building the place list, a saved list of rooms, room booking, double-booking checks, embedded maps, and a picker on groups, teams, or families. Those features may store a `location_id` later. They are not part of this RFC.

## 3. Decisions

| # | Decision |
|---|---|
| D1 | **The usual room belongs to the event type and the building together.** A type stores one or more usual places, and each of those places has its own room for that type. Worship at North can start in the Sanctuary. The potluck at North can start in the Hall. Worship at South can start in a different room. The room is text copied onto the event. It is not its own row. |
| D2 | **Creating an event fills in what it can.** One usual place fills in that place and its room. Several usual places ask which place first. The list is every active place, with that type's usual places first. Choosing one of the type's usual places fills in that pair's room. Choosing any other place leaves the room empty. No usual place leaves both empty. An inactive usual place is not offered, and the field starts empty with no note. On a new event, changing the type updates the place and the room only while those fields are still untouched. Once someone edits a field, that field stays. Changing the type on a saved event does not change its place or room. |
| D3 | **Saving a new usual place or room asks before it rewrites the calendar.** The choice is: update events already on the calendar, or apply it only to events created from now on. Updating the calendar changes future events that still have that type, that place, and the old room. Anything set somewhere else stays. Past events stay. |
| D4 | **The place flag decides the street address. The event decides who is looking.** Staff always see the address. When the place hides it and someone is scheduled to volunteer for that event, those volunteers also see it, and everyone else sees the name and the room. When the place hides it and nobody is scheduled, anyone who can open the event sees the address. An external feed never includes a hidden address or that place's name. Its location text is "Contact the leader for more info." A visible place includes its name, the room, and the address on the feed, and never a link. Volunteer emails follow the same rule as the screen. Directions use the address only for someone who is allowed to see it. |
| D5 | **No place is valid.** `location_id = 0` can be saved. A link and no place shows "Online" and the link inside ChurchCRM. A place and a link show the place, the room, and the link. The link is never written to an external feed. An event with a link and no place shows "Contact the leader for more info" on that feed. |
| D6 | **A repeated set can be edited one date at a time.** Repeat creation already makes separate event rows and stores no series id. Give the rows created together a shared series id. Changing the place or the room asks: this event, or this event and the following events in that series. Past events stay. The type's usual place and room do not change. |
| D7 | **Quick-add uses the place API and requires Manage places.** From either event editor, a person who can manage places may add one without leaving the page. The form asks for the name, the address, and whether members can see the address. That posts to `POST /api/locations` ([RFC 0006](0006-places.md)) and selects the new place. The address choice defaults to shown. A person who can edit the event but cannot manage places picks from the list and does not see Add new. Their picker list omits a street address the place hides. |

## 4. Surface

- Pickers on `/event/editor` and `/event/repeat-editor`. The first option is "No location". An inactive place already on the event stays in the list, labeled inactive. Active places only, otherwise.
- The event type editor gains the usual places and the room for each.
- Dashboard badge shows the place and the room when set.
- The event page shows the name, the room, the address, phone and email links, an inactive label, and directions. Member screens follow D4 and D5.
- Filter `?location=<id>`. `All` is the default. `none` is `location_id = 0`.
- Output goes through `InputUtils::escapeHTML()` / `escapeAttribute()`.

## 5. Delivery

Two PRs, after [RFC 0006](0006-places.md) is merged. Each is usable on its own.

| PR | Contents | Tests |
|---|---|---|
| 1. Editors | Usual place and room on the type (D1 to D3), both pickers, quick-add (D7), series id and the this-or-following prompt (D6), dashboard badge, event page card, filter | Cypress specs in `event-editor.spec.js`, `repeat-event-editor.spec.js`, and a dashboard filter spec |
| 2. Members | Portal, both feeds, and volunteer email follow D4 and D5. `VolunteerNotificationService` calls `getLocationName()`. | Cypress feed spec |

UI follows `frontend-development.md` and attaches screenshots. Strings use `gettext()` / `i18next.t()`. The docs tracking issue is the one opened for the event screens.

## 6. Old issues

Close these when this RFC is accepted. They stay open until then. #8575 to #8579 close from [RFC 0006](0006-places.md).

| Issue | Outcome |
|---|---|
| #5238 epic | Closed when this RFC and RFC 0006 are both accepted |
| #8580 picker | Closed. §4. PR 1 |
| #8581 dashboard badge and event card | Closed. §4. PR 1 |
| #8582 filter | Closed. §4. PR 1 |
| #8583 menu, i18n, release notes | The Places menu is RFC 0006. The event-screen strings and release note are this RFC |

File the two implementation issues when this RFC is accepted.
