Demo event import files

Files:
- `calendars.csv` — calendar definitions (id, name, slug, timezone)
- `events.csv` — event rows. Columns used: id, calendar_id, title, start, end, all_day, location_name, description, category, rrule, exdate, external_url
- `locations.csv` — optional location records referenced by `location_name`/`location_id`
- `attendees.csv` — optional registrations/attendees linked by `event_id`

Notes:
- Use `id` or an `external_id` field in your importer to avoid duplicates on repeated imports.
- `rrule` supports iCal RRULE strings for recurrences (e.g., `FREQ=WEEKLY;BYDAY=SU;COUNT=12`).
- `exdate` accepts comma-separated ISO dates to skip specific occurrences.
- All-day events should set `all_day=true` and provide date-only `start`/`end` (YYYY-MM-DD).
- Times without timezones assume the calendar's `timezone`.

Suggested demo: 10 events included — `evt-1` is Sunday School and `evt-2` is Sunday Service. Use `/admin/demo` CSVs to preview the import.
