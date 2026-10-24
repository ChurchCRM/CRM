-- #10730: Add an index for pledge group lookups on existing installations.
-- An interrupted upgrade can rerun this script; avoid a duplicate-index error.
-- information_schema works on MySQL and MariaDB (unlike ADD INDEX IF NOT EXISTS).
SET @pledge_groupkey_index_sql = (
    SELECT IF(
        COUNT(*) > 0,
        'SELECT 1',
        'ALTER TABLE `pledge_plg` ADD INDEX `idx_plg_GroupKey` (`plg_GroupKey`)'
    )
    FROM information_schema.statistics
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pledge_plg'
      AND INDEX_NAME = 'idx_plg_GroupKey'
);
PREPARE pledge_groupkey_index_stmt FROM @pledge_groupkey_index_sql;
EXECUTE pledge_groupkey_index_stmt;
DEALLOCATE PREPARE pledge_groupkey_index_stmt;
