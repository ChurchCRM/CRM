-- ChurchCRM 7.6.0 — Add plg_FYID index on pledge_plg for fiscal-year query performance
-- Resolves: GitHub Issue #9378 (Audit and standardize fiscal-year vs. lifetime scoping)
--
-- The plg_FYID column is now used in WHERE clauses by the /api/payments/family/{id}/list
-- and FamilyPledgeSummaryService queries. Adding an index avoids full table scans on
-- large installations.
--
-- Uses an information_schema guard to be idempotent (MySQL 8+ / MariaDB-compatible).
-- `CREATE INDEX IF NOT EXISTS` is a MariaDB extension and not supported by MySQL 8.x.

SET @_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pledge_plg'
      AND INDEX_NAME = 'plg_FYID'
);

SET @_sql = IF(
    @_index_exists > 0,
    'DO 0',
    'ALTER TABLE `pledge_plg` ADD INDEX `plg_FYID` (`plg_FYID`)'
);

PREPARE _stmt FROM @_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
