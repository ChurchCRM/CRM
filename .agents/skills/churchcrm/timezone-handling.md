---
title: "Timezone Handling"
intent: "End-to-end timezone conventions for events, calendars, kiosks, and any datetime-aware feature"
tags: ["timezone", "frontend", "database"]
prereqs: ["[[frontend-development]]"]
complexity: "advanced"
---
# Timezone Handling

Conventions for events, calendars, kiosks, and any church-time-aware feature. Read before changing datetime code in the editor, calendar, kiosk, or related JS.

## Timezones in play

| # | Layer | Where set |
|---|-------|-----------|
| 1 | Browser tz | OS / `Intl.DateTimeFormat().resolvedOptions().timeZone` |
| 2 | System config `sTimeZone` | `config_cfg` row, set in admin UI (church location, e.g. `America/Detroit`) |
| 3 | PHP default tz | `Bootstrapper` calls `date_default_timezone_set()` with `sTimeZone`; equals #2 after bootstrap |
| 4 | MySQL server tz | OS / `default-time-zone` (usually UTC in Docker) |
| 5 | MySQL connection tz | Inherits #4; `Bootstrapper::buildConnectionManagerConfig()` sets only `sql_mode` |

Because PHP's default tz equals `sTimeZone`, Propel hydrates DATETIME values as wall-clock-in-`sTimeZone`. Storage therefore follows the same convention.

## Storage convention

Event DATETIME columns store naive wall-clock-in-`sTimeZone`. `event_start = '2026-04-24 22:00:00'` means 10 PM at the church, whoever entered it. Propel's hydration (`new DateTime($stored, null)`) returns the same wall-clock numbers in `sTimeZone`, so `$event->getStart()->format('g:i A')` stays correct.

## Frontend editor

In `webpack/event-form.js`, `event.Start` and `event.End` are church wall-clock strings throughout (`YYYY-MM-DDTHH:mm:ss` for timed, `YYYY-MM-DD` for all-day). Date logic runs on those strings, because `new Date()`, `getHours()` and `getDate()` read the browser's tz and shift wall-clock numbers when it differs from the church's.

Convert at boundaries with the helpers in `event-form.js`:

| Helper | Use |
|--------|-----|
| `getChurchTz()` | `window.CRM.calendarJSArgs?.sTimeZone \|\| window.CRM.timeZone \|\| "UTC"`. `calendarJSArgs` exists on the calendar page only; `window.CRM.timeZone` is set on every page by `Include/Header.php` |
| `formatJsDateInChurchTz(date, tz, dateOnly)` | Formats a JS Date as `YYYY-MM-DDTHH:mm:ss` with Intl `timeZone: tz` |
| `toWallClockString(value)` | Coerces a Date, API string, or ISO string (offset or Z) to a church wall-clock string; strips Propel space format, microseconds, tz suffix |
| `toWallClockDate(value)` | Date-only variant returning `YYYY-MM-DD` |

```js
// API "2026-04-24 20:00:00.000000" -> toWallClockString -> "2026-04-24T20:00:00"
// datetime-local input "2026-04-24T20:00" -> used directly, padded to seconds before send

const body = JSON.stringify(event, (_key, value) =>
  value instanceof Date ? formatJsDateInChurchTz(value, getChurchTz(), false) : value);
```

## FullCalendar

### Cell clicks

`info.start` is midnight UTC. Read `info.startStr` / `info.endStr`, which are already in the calendar tz (`"2026-04-24"` all-day, `"2026-04-24T09:00:00-04:00"` timed):

```js
const event = {
  Start: info.startStr ? toWallClockString(info.startStr) : undefined,
  End: info.endStr ? toWallClockString(info.endStr) : undefined,
};
```

### Event Dates are markers

`event.start`, `info.event.start` and `info.oldEvent.start` are marker Dates: their UTC components hold the wall-clock-in-tz (a 10 PM Detroit event has `getUTCHours() === 22` in any browser tz). Display them by reading UTC components:

```js
new Intl.DateTimeFormat(undefined, { timeZone: "UTC", /* ... */ }).format(event.start);
// -> 10:00 PM, matching the calendar grid
```

### Today highlight

Pass `now` built from the church tz so the highlighted day follows church time:

```js
const churchNow = (() => {
  const tz = window.CRM.calendarJSArgs.sTimeZone;
  if (!tz) return new Date();
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz, year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false,
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value || "00";
  const hh = get("hour") === "24" ? "00" : get("hour");
  return new Date(`${get("year")}-${get("month")}-${get("day")}T${hh}:${get("minute")}:${get("second")}`);
})();

new FullCalendar.Calendar(el, {
  timeZone: window.CRM.calendarJSArgs.sTimeZone || "local",
  now: churchNow,
});
```

### Drag-drop

Send wall-clock strings: take `evt.startStr` / `evt.endStr` (calendar tz) and strip the offset:

```js
const stripTz = (s) => (typeof s === "string" ? s.replace(/(?:Z|[+-]\d{2}:\d{2})$/, "") : s);
data: JSON.stringify({
  startTime: evt.allDay ? evt.startStr : stripTz(evt.startStr),
  endTime: evt.end ? stripTz(evt.endStr) : null,
}),
```

## Propel datetime strings

`$event->toArray()` / `toJSON()` serialize DATETIME as `"2026-04-24 20:00:00.000000"`. Chrome parses that as UTC, shifting the moment by the browser offset. Normalize before `new Date()`:

```js
function normalizeApiDateString(value) {
  if (typeof value !== "string") return value;
  return value
    .replace(" ", "T")
    .replace(/\.\d+/, "")
    .replace(/(?:Z|[+-]\d{2}:\d{2})$/, "");
}
```

Endpoints that hand an instant to a JS consumer emit `format('c')` (ISO 8601 with the `sTimeZone` offset, e.g. `"2026-04-24T20:00:00-04:00"`), which moment and `new Date()` parse as an instant.

## Kiosk timing

`KioskDevice::heartbeat()` adds ISO-with-offset fields to the Assignment JSON so kiosk JS compares instants on any device tz:

```php
'Event' => [
    'Start'          => $event->getStart()->format('c'),
    'End'            => $event->getEnd()->format('c'),
    'CheckInOpensAt' => (clone $event->getStart())->modify('-1 hour')->format('c'),
],
```

The frontend reads `moment(Assignment.Event.CheckInOpensAt)`. Check-in opens one hour before the event start so volunteers can pre-check-in.

## Cross-tz banner

Editor surfaces show a quiet notice when browser tz differs from church tz, for timed events only:

```js
const churchTz = getChurchTz();
const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
if (churchTz && churchTz !== "UTC" && browserTz !== churchTz) {
  // <i class="fa-solid fa-triangle-exclamation text-warning"></i>
  // "Times use church time (America/Detroit), not your browser (America/Los_Angeles)."
}
```

Render the markup with `id="eventTzNotice"` and toggle `d-none` in the day-type radio handler, so the form is not re-rendered. Style the hint as plain text with `text-muted` / `text-warning-emphasis`; Tabler `.alert` chrome with inline children renders visible gaps.

## Rules

- Normalize Propel strings before `new Date()`
- Display FullCalendar marker Dates through Intl with `timeZone: "UTC"`
- Send wall-clock strings to the backend (`startStr` with the offset stripped)
- Store wall-clock-in-`sTimeZone` in DATETIME columns
- Read FullCalendar marker Dates with `getUTCHours()` / `getUTCDate()`
- Carry `event.Start` / `event.End` as strings through to PHP

## When to use this card

- Changes to `webpack/event-form.js`, `webpack/event-calendars.js`, `webpack/calendar-event-editor.js`, `webpack/kiosk/kiosk-jsom.ts`
- Changes to `KioskDevice::heartbeat()` or the kiosk device routes
- New datetime-aware features in the editor, calendar, or kiosk
- "Time shows wrong" reports from cross-tz users
- Changes to the event API (`src/api/routes/calendar/events.php`)
