# Timezone Handling — ChurchCRM

End-to-end timezone conventions for events, calendars, kiosks, and any other church-time-aware feature. Built from the PR #8806 timezone refactor. **Read this before touching any datetime in the editor, calendar, kiosk, or related JS.**

---

## The five timezones in play

When debugging a tz issue, identify which of these is involved:

| # | Layer | Where set | Typical value |
|---|-------|-----------|---------------|
| 1 | **Browser tz** | OS / `Intl.DateTimeFormat().resolvedOptions().timeZone` | User's local — e.g. PDT for an admin in California |
| 2 | **System config `sTimeZone`** | DB `config_cfg` row, set in admin UI | Church location — e.g. `America/Detroit` |
| 3 | **PHP default tz** | `Bootstrapper.php:284` calls `date_default_timezone_set($timezone)` with `SystemConfig::getValue('sTimeZone')` | **Equals #2 after bootstrap** |
| 4 | **MySQL server tz** | OS / `default-time-zone` in `my.cnf` | Usually UTC in Docker; `SHOW VARIABLES LIKE 'time_zone'` |
| 5 | **MySQL connection tz** | NOT overridden (`Bootstrapper::buildConnectionManagerConfig()` only sets sql_mode) | Inherits #4 |

**Critical implication:** Because PHP default tz == sTimeZone after bootstrap, Propel hydrates DATETIME values as wall-clock-in-sTimeZone. So the storage convention MUST be wall-clock-in-sTimeZone — anything else (e.g. UTC) double-shifts on read.

---

## Storage convention (do not change)

**All event DATETIME columns store wall-clock-in-sTimeZone**, naive (no tz info in the stored value).

- `event_start = '2026-04-24 22:00:00'` means **22:00 (10 PM) at the church**, regardless of who entered it.
- 75+ historical events (back to 2016) follow this convention. Changing it requires data migration.
- Propel's hydration (`new DateTime($stored, null)`) uses `date_default_timezone_get()` (= sTimeZone) so the wall-clock numbers come back as the same wall-clock-in-sTimeZone DateTime object.

**Never store UTC.** It looks tempting but breaks every historical event and every line of PHP that does `$event->getStart()->format('g:i A')`.

---

## Frontend: Editor architecture <!-- learned: 2026-04-25 -->

In `webpack/event-form.js`, `event.Start` and `event.End` are **church wall-clock STRINGS** throughout (`YYYY-MM-DDTHH:mm:ss` for timed, `YYYY-MM-DD` for all-day). **Never JS Date objects in date logic.**

Why: `new Date()`, `getHours()`, `getDate()` all interpret in the BROWSER's tz, which silently shifts wall-clock numbers when browser tz ≠ church tz.

Conversions happen at boundaries via these helpers:

| Helper | Use |
|--------|-----|
| `getChurchTz()` | Returns `window.CRM.calendarJSArgs?.sTimeZone \|\| window.CRM.timeZone \|\| "UTC"` — calendarJSArgs is calendar-page only, `window.CRM.timeZone` is set on every page by `Include/Header.php:114` |
| `formatJsDateInChurchTz(date, tz, dateOnly)` | Formats a JS Date as `YYYY-MM-DDTHH:mm:ss` using Intl with `timeZone: tz` |
| `toWallClockString(value)` | Coerces any input (Date, API string, ISO with offset, ISO with Z) to a wall-clock string in church tz. Strips Propel space format / microseconds / tz suffix |
| `toWallClockDate(value)` | Date-only variant returning `YYYY-MM-DD` |

```js
// API loaded:  "2026-04-24 20:00:00.000000" (Propel space format)
// → toWallClockString → "2026-04-24T20:00:00"  (numbers preserved)

// FullCalendar Date passed in:
// → toWallClockString → formatted in church tz via Intl

// User input from datetime-local field:
// → already a string "2026-04-24T20:00" — used directly, padded to seconds before send

// JSON.stringify save replacer (fallback for stray Date objects):
const body = JSON.stringify(event, (_key, value) => {
  if (value instanceof Date) return formatJsDateInChurchTz(value, getChurchTz(), false);
  return value;
});
```

---

## FullCalendar quirks <!-- learned: 2026-04-25 -->

### Cell click `info.start` is midnight UTC, not midnight in calendar tz

For an all-day cell click in month view:

- ❌ `info.start.getDate()` / `getHours()` use browser-local components — shifts back by browser offset (April 24 click → April 23 5 PM in PDT).
- ✅ Use `info.startStr` instead — already in calendar's configured tz: `"2026-04-24"` for all-day or `"2026-04-24T09:00:00-04:00"` for timed click.

```js
// In showNewEventForm:
const event = {
  Start: info.startStr ? toWallClockString(info.startStr) : undefined,
  End: info.endStr ? toWallClockString(info.endStr) : undefined,
};
```

### Event Date objects are "markers", not real moments

`event.start` (and `info.event.start`, `info.oldEvent.start`) is a **marker Date**: a JS Date whose **UTC components** encode the wall-clock-in-tz. So a 10 PM Detroit event has `event.start.getUTCHours() === 22` regardless of browser tz.

```js
// ❌ WRONG — toLocaleString uses browser tz, shifts the displayed time:
const display = event.start.toLocaleString();  // "8:00 PM" on PDT

// ❌ WRONG — Intl with sTimeZone re-applies the offset, shifts twice:
new Intl.DateTimeFormat(undefined, { timeZone: "America/Detroit", ... }).format(event.start);
// → 6 PM (= 22:00 UTC marker − 4 EDT offset)

// ✅ CORRECT — read marker UTC components verbatim:
new Intl.DateTimeFormat(undefined, { timeZone: "UTC", ... }).format(event.start);
// → 10:00 PM (matches what the calendar grid shows)
```

### Today highlight uses browser-local date by default

`now: undefined` falls back to actual moment, but FC reads date components in a way that picks up browser-local. To force church-tz "today":

```js
const churchNow = (() => {
  const tz = window.CRM.calendarJSArgs.sTimeZone;
  if (!tz) return new Date();
  // Build a Date whose local components match church-tz wall-clock right now
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz, year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false,
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value || "00";
  const hh = get("hour") === "24" ? "00" : get("hour");
  return new Date(`${get("year")}-${get("month")}-${get("day")}T${hh}:${get("minute")}:${get("second")}`);
})();

window.CRM.fullcalendar = new FullCalendar.Calendar(el, {
  timeZone: window.CRM.calendarJSArgs.sTimeZone || "local",
  now: churchNow,
  // ...
});
```

### Drag-drop must send wall-clock, not UTC

`evt.start.toISOString()` returns UTC + Z, which PHP's DateTime then parses as UTC and Propel formats as such — breaking storage. Use `evt.startStr` (already calendar-tz) and strip the offset:

```js
const stripTz = (s) => (typeof s === "string" ? s.replace(/(?:Z|[+-]\d{2}:\d{2})$/, "") : s);
data: JSON.stringify({
  startTime: evt.allDay ? evt.startStr : stripTz(evt.startStr),
  endTime: evt.end ? stripTz(evt.endStr) : null,
}),
```

---

## Propel space format Chrome misparse <!-- learned: 2026-04-25 -->

`$event->toArray()` and `$event->toJSON()` serialize DATETIME columns via `format('Y-m-d H:i:s.u')` → `"2026-04-24 20:00:00.000000"`.

**Chrome `new Date("2026-04-24 20:00:00.000000")` parses this as UTC.** Other browsers vary. So a "20:00 Detroit wall-clock" stored value, fed straight into JS, becomes a moment shifted by the browser's UTC offset.

Always normalize before `new Date()`:

```js
function normalizeApiDateString(value) {
  if (typeof value !== "string") return value;
  return value
    .replace(" ", "T")          // → "2026-04-24T20:00:00.000000"
    .replace(/\.\d+/, "")       // → "2026-04-24T20:00:00"
    .replace(/(?:Z|[+-]\d{2}:\d{2})$/, "");  // strip any tz suffix
}
```

For kiosk and other endpoints that hand raw ISO to a JS consumer, prefer **emitting `format('c')`** server-side instead — gives proper ISO 8601 with sTimeZone offset (e.g. `"2026-04-24T20:00:00-04:00"`), which moment / `new Date()` parse correctly as instants.

---

## Kiosk timing <!-- learned: 2026-04-25 -->

`KioskDevice::heartbeat()` enriches the Assignment JSON with three ISO+offset fields so kiosk JS can do tz-correct comparisons regardless of device tz:

```php
'Event' => [
    ...
    'Start'           => $event->getStart()->format('c'),
    'End'             => $event->getEnd()->format('c'),
    'CheckInOpensAt'  => (clone $event->getStart())->modify('-1 hour')->format('c'),
],
```

Frontend uses `moment(Assignment.Event.CheckInOpensAt)` — moment parses ISO 8601 with offset as an instant, so `now.isBefore(checkInOpensAt)` works on any device tz.

**Kiosk check-in opens 1 hour before event start** (server-computed `CheckInOpensAt`) so volunteers can pre-check-in.

---

## Cross-tz UX banner

Editor surfaces show a quiet warning when browser tz ≠ church tz — only on **timed** events (all-day has no time component):

```js
const churchTz = getChurchTz();
const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
if (churchTz && churchTz !== "UTC" && browserTz !== churchTz) {
  // Render: <i ti-alert-triangle text-warning></i>
  //         "Times use church time (America/Detroit), not your browser (America/Los_Angeles)."
}
```

Render the markup unconditionally with `id="eventTzNotice"` and toggle `d-none` in the day-type radio handler — avoids re-rendering the form on every toggle.

---

## Anti-patterns (do NOT do these)

- ❌ `new Date(propelString)` without normalization → Chrome UTC misparse
- ❌ `event.start.toLocaleString()` for display → browser-tz shift
- ❌ `event.start.toISOString()` to send to backend → UTC string PHP misinterprets
- ❌ Storing UTC in DATETIME columns → breaks 75+ historical events
- ❌ `getHours()` / `getDate()` on FullCalendar event Dates → reads marker UTC components as if they were local
- ❌ Date-based logic in `event.Start` / `event.End` → use STRINGS through to PHP

## When to use this skill

- Any change to `webpack/event-form.js`, `webpack/event-calendars.js`, `webpack/calendar-event-editor.js`, `webpack/kiosk/kiosk-jsom.ts`
- Any change to `KioskDevice::heartbeat()` or the kiosk device routes
- Adding a new datetime-aware feature in the editor / calendar / kiosk
- Debugging a "time shows wrong" report from a cross-tz user
- Modifying the event API (`src/api/routes/calendar/events.php`)

## Tabler / icon gotchas learned in this session <!-- learned: 2026-04-25 -->

- `ti-alert-triangle-filled` is NOT in the Tabler Icons release used here — use `ti-alert-triangle` (non-filled) for the warning icon.
- Tabler `.alert` chrome with inline `<strong>` / `<span>` children renders with visible visual gaps that read as columns. For low-key hints use plain text + `text-muted` / `text-warning-emphasis` instead of the alert wrapper.
