-- ChurchCRM 7.7.0 — Convert the events tables to utf8mb4
-- Closes #9736: an emoji in an event title returns HTTP 500
--
-- `events_event` and `event_types` were created with MySQL's 3-byte `utf8`
-- (utf8mb3). Any 4-byte character — every emoji — makes the INSERT fail with
-- SQLSTATE[22007] "Incorrect string value", surfacing as a 500 from
-- POST /api/events (and from the event-type editor for `type_name`).
--
-- This mirrors the `note_nte` conversion in 7.3.1-cleanup.sql (#8856) and the
-- project rule in .agents/skills/churchcrm/db-schema-migration.md:
-- "Any table that stores user-generated text must use utf8mb4_unicode_ci".
--
-- Deliberately scoped to the events tables. A fresh install still has ~50 other
-- utf8mb3 tables (person_per, family_fam, group_grp, calendars, …); converting
-- those is tracked separately.
--
-- Safety: both tables are keyed only on their INTEGER primary key and neither
-- participates in a foreign key, so widening the character columns cannot
-- overflow InnoDB's 3072-byte index limit or break an FK charset match. The two
-- guards below assert that on the live schema before anything is altered — if a
-- site added a long index or a character-column FK, the upgrade aborts with a
-- named error instead of half-converting the schema.

-- Guard 1: no index on these tables may exceed InnoDB's 3072-byte key limit
-- once every character column is charged 4 bytes per character.
SET @events_max_key_bytes := (
    SELECT IFNULL(MAX(k.key_bytes), 0)
    FROM (
        SELECT s.TABLE_NAME, s.INDEX_NAME,
               SUM(
                   CASE
                       WHEN c.CHARACTER_MAXIMUM_LENGTH IS NULL THEN 8
                       ELSE IFNULL(s.SUB_PART, c.CHARACTER_MAXIMUM_LENGTH) * 4
                   END
               ) AS key_bytes
        FROM information_schema.STATISTICS s
        JOIN information_schema.COLUMNS c
          ON c.TABLE_SCHEMA = s.TABLE_SCHEMA
         AND c.TABLE_NAME   = s.TABLE_NAME
         AND c.COLUMN_NAME  = s.COLUMN_NAME
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME IN ('events_event', 'event_types')
        GROUP BY s.TABLE_NAME, s.INDEX_NAME
    ) k
);

SET @events_guard_sql := IF(
    @events_max_key_bytes > 3072,
    'SELECT 1 FROM `ABORT_events_utf8mb4_index_would_exceed_3072_bytes`',
    'DO 0'
);
PREPARE events_guard FROM @events_guard_sql;
EXECUTE events_guard;
DEALLOCATE PREPARE events_guard;

-- Guard 2: no foreign key may join these tables on a character column — the
-- charset of both sides would have to change together.
SET @events_char_fk_count := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE u
    JOIN information_schema.COLUMNS c
      ON c.TABLE_SCHEMA = u.TABLE_SCHEMA
     AND c.TABLE_NAME   = u.TABLE_NAME
     AND c.COLUMN_NAME  = u.COLUMN_NAME
    WHERE u.TABLE_SCHEMA = DATABASE()
      AND u.REFERENCED_TABLE_NAME IS NOT NULL
      AND c.CHARACTER_SET_NAME IS NOT NULL
      AND (
            u.TABLE_NAME IN ('events_event', 'event_types')
         OR u.REFERENCED_TABLE_NAME IN ('events_event', 'event_types')
      )
);

SET @events_fk_guard_sql := IF(
    @events_char_fk_count > 0,
    'SELECT 1 FROM `ABORT_events_utf8mb4_character_column_foreign_key`',
    'DO 0'
);
PREPARE events_fk_guard FROM @events_fk_guard_sql;
EXECUTE events_fk_guard;
DEALLOCATE PREPARE events_fk_guard;

-- Convert. CONVERT TO CHARACTER SET rewrites the table default and every
-- character column, so `event_title`, `event_desc`, `event_text`, `event_url`
-- and `type_name` all accept 4-byte characters afterwards.
ALTER TABLE `events_event`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `event_types`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
