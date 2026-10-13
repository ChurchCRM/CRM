Demo event import files

Files:
- `events.csv` — event rows. Columns: `id`, `category`, `title`, `description`, `event_text`,
  `start_offset_days`, `start_time`, `duration_minutes`, `link_groups`, `attended_fraction`,
  `inactive`, `external_url`, `calendars`, `weekday`
- `calendars.csv` — church calendars the events are pinned to. See "Calendars" below.
- `volunteers.json` — Volunteer v2 ministries and schedules that staff the events. See
  "Volunteer v2 data" below.
- `locations.csv` — optional location records (currently not imported)
- `attendees.csv` — **no longer imported**; attendance is now seeded from real group memberships
  using `attended_fraction`. See notes below.

All three are imported with the *Include events and calendars* option.

## Column reference (`events.csv`)

| Column | Description |
|---|---|
| `id` | Stable row identifier (not imported into DB) |
| `category` | Maps to `event_types.type_name`; type is created if it doesn't exist |
| `title` | Event title |
| `description` | Short description (varchar 255) |
| `event_text` | Rich HTML body |
| `start_offset_days` | Days from today (negative = past, 0 = today, positive = future). When present and numeric, overrides `start`/`end`. |
| `start_time` | `HH:MM:SS` local start time (falls back to `09:00:00`) |
| `duration_minutes` | Event length in minutes; `event_end = start + duration` |
| `link_groups` | `;`-separated demo group names to link via `event_audience` |
| `attended_fraction` | 0.0–1.0; fraction of each linked group's members to check in. Only applied to past events (`event_end < now`). |
| `inactive` | `true` / `false` |
| `external_url` | Optional external URL |
| `calendars` | `;`-separated `calendars.csv` ids the event is pinned to; empty = on no calendar (shown under "Unpinned events") |
| `weekday` | Optional day name (`Sunday` … `Saturday`). Moves the event to the first such day on or after `start_offset_days`, so a Sunday service lands on a Sunday whatever day the import runs. Rows 7 days apart stay a week apart. |

## Calendars

`calendars.csv` columns: `id`, `name`, `slug`, `description`, `timezone`, `background_color`,
`foreground_color`, `portal`. A church calendar with the same name is reused, so a repeated import
adds none. The rows with `portal` = `true` become the calendars the Member Portal shows
(Admin → Member Portal → Calendars), replacing whatever it showed before. `slug`, `description` and
`timezone` are not stored.

## Volunteer v2 data

`volunteers.json` turns into ministries, teams, positions, qualified volunteers, coordinators and
team leaders, schedules and assignments, built through the volunteer services as the administrator
running the import. It is imported only while Volunteer Management V2 is on, which `config.json`
does (`sVolunteerVersion`).

- People are named by their `people.json` email.
- The first team of a ministry renames the team the ministry is created with.
- A team with a `class` is linked to that Sunday School class; its teachers are qualified for
  `teacherPosition`. A ministry with `sundaySchool` is imported only with the *Include Sunday School*
  option.
- A schedule follows the events of `eventType` titled `title`, or the events linked to `class`.
  Its occurrences are generated up to the scheduling horizon, so the events it follows must be
  within it. `defaults` are the volunteers it assigns to every occurrence.
- `assignments` name the schedule's occurrence by number, counting from 1, and a `status` of
  `accepted`, `pending` or `declined` (recorded by the coordinator).
- A ministry that already exists is skipped, so a repeated import adds no volunteer data.
- No email is sent for any of it: everything the import queues, and the reminders and
  unfilled-position alerts the background jobs would queue for it, is recorded as skipped.

## Attendance seeding

`attended_fraction` is used by `DemoDataService::seedEventAttendance()` to create realistic
mixed attendance for already-ended events:

- Members of the linked groups (via `person2group2role_p2g2r`) are loaded, ordered by person ID.
- The first `ceil(fraction × memberCount)` distinct persons receive an `event_attend` row with
  `checkin_date` set (= event end time).
- **The remaining members receive NO `event_attend` row** — they surface as "Did Not Attend"
  in PR #8994's card query (`event_attend.event_id IS NULL`).
- `attended_fraction = 0` or future events: no attendance rows are created.

`attendees.csv` is kept for reference only. Its fictional names/emails do not match `people.json`
and cannot be linked to real person records; it is not read by the importer.

## Group links

Groups in `link_groups` must exist in `groups.json` and be imported:

- **Sunday School groups** (`isSundaySchool: true` in `groups.json`, e.g. `Angels class`,
  `Youth Meeting`) are only imported when the *Include Sunday School* option is checked.
  If the group is not found, a warning is logged and the link is skipped (no error).
- **Non–Sunday-School groups** (`Worship Service`, `Boys Scouts`, `Girl Scouts`,
  `Church Board`, `Clergy`) are always imported.

This ensures the "Did Not Attend" card (PR #8994) works even when Sunday School is excluded,
because `evt-past-worship` is linked to `Worship Service` (always present).

## Notes

- `start`/`end` absolute columns are supported as a fallback when `start_offset_days` is absent
  or non-numeric (backwards compatibility for custom CSV files).
- Use `id` or an `external_id` field in your importer to avoid duplicates on repeated imports.
- The demo has 25 events: 4 past (group-linked, with attendance), 2 today, and 19 future,
  including four weekly Sunday services and Sunday School classes for the volunteer schedules.
