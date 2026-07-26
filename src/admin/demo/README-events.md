Demo event import files

Files:
- `events.csv` — event rows. Columns: `id`, `category`, `title`, `description`, `event_text`,
  `start_offset_days`, `start_time`, `duration_minutes`, `link_groups`, `attended_fraction`,
  `inactive`, `external_url`
- `calendars.csv` — calendar definitions (currently not imported)
- `locations.csv` — optional location records (currently not imported)
- `attendees.csv` — **no longer imported**; attendance is now seeded from real group memberships
  using `attended_fraction`. See notes below.

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
- Suggested demo: 12 events — 4 past (group-linked, with attendance), 2 today, 6 future.
