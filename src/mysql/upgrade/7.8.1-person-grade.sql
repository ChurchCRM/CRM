-- #10189: school grade on each person, from a church-defined list.
--
-- per_grd_ID is the lst_OptionID of the person's grade in the grades list.
-- NULL means no grade, so every existing person starts without one.
-- A grade's lst_OptionID is its level, the typical age at the start of the
-- school year (Pre-K 4 ... 12th 17), so switching to another country's
-- grade names renames options without touching anyone's grade.

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'person_per' AND COLUMN_NAME = 'per_grd_ID') = 0,
  'ALTER TABLE `person_per` ADD COLUMN `per_grd_ID` mediumint(8) unsigned DEFAULT NULL AFTER `per_NeedsReview`',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'person_per' AND INDEX_NAME = 'idx_per_grd_ID') = 0,
  'ALTER TABLE `person_per` ADD INDEX `idx_per_grd_ID` (`per_grd_ID`)',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;
