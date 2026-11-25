-- Store 4-byte characters (emoji) in family and person fields (#10465).
-- Aborts before altering if an index or foreign key would break under utf8mb4.

-- No index may exceed InnoDB's 3072-byte key limit at 4 bytes per character.
SET @people_max_key_bytes := (
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
          AND s.TABLE_NAME IN ('family_fam', 'person_per')
        GROUP BY s.TABLE_NAME, s.INDEX_NAME
    ) k
);

SET @people_guard_sql := IF(
    @people_max_key_bytes > 3072,
    'SELECT 1 FROM `ABORT_people_utf8mb4_index_would_exceed_3072_bytes`',
    'DO 0'
);
PREPARE people_guard FROM @people_guard_sql;
EXECUTE people_guard;
DEALLOCATE PREPARE people_guard;

-- No foreign key may join these tables on a character column.
SET @people_char_fk_count := (
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
            u.TABLE_NAME IN ('family_fam', 'person_per')
         OR u.REFERENCED_TABLE_NAME IN ('family_fam', 'person_per')
      )
);

SET @people_fk_guard_sql := IF(
    @people_char_fk_count > 0,
    'SELECT 1 FROM `ABORT_people_utf8mb4_character_column_foreign_key`',
    'DO 0'
);
PREPARE people_fk_guard FROM @people_fk_guard_sql;
EXECUTE people_fk_guard;
DEALLOCATE PREPARE people_fk_guard;

ALTER TABLE `family_fam`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `person_per`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
