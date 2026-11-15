-- #10485: protect durable email history. Email rows cannot be deleted when
-- their recipient person or family is deleted. Existing orphaned references
-- are nulled without destroying historical communication records.
-- Safe to re-run: updates are conditional and constraints are checked.

-- BEGIN: email history follows its person and family
UPDATE `email_log_eml` e
LEFT JOIN `person_per` p ON p.`per_ID` = e.`eml_per_ID`
SET e.`eml_per_ID` = NULL
WHERE e.`eml_per_ID` IS NOT NULL AND p.`per_ID` IS NULL;

UPDATE `email_log_eml` e
LEFT JOIN `family_fam` f ON f.`fam_ID` = e.`eml_fam_ID`
SET e.`eml_fam_ID` = NULL
WHERE e.`eml_fam_ID` IS NOT NULL AND f.`fam_ID` IS NULL;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND COLUMN_NAME = 'eml_per_ID'
       AND COLUMN_TYPE = 'mediumint(9) unsigned') = 0,
    'ALTER TABLE `email_log_eml` MODIFY `eml_per_ID` mediumint(9) unsigned DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND COLUMN_NAME = 'eml_fam_ID'
       AND COLUMN_TYPE = 'mediumint(9) unsigned') = 0,
    'ALTER TABLE `email_log_eml` MODIFY `eml_fam_ID` mediumint(9) unsigned DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

-- Replace an existing CASCADE key from an earlier attempt with RESTRICT.
SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_person' AND DELETE_RULE = 'CASCADE') > 0,
    'ALTER TABLE `email_log_eml` DROP FOREIGN KEY `fk_eml_person`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_family' AND DELETE_RULE = 'CASCADE') > 0,
    'ALTER TABLE `email_log_eml` DROP FOREIGN KEY `fk_eml_family`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_person' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE `email_log_eml` ADD CONSTRAINT `fk_eml_person` FOREIGN KEY (`eml_per_ID`) REFERENCES `person_per` (`per_ID`) ON DELETE RESTRICT',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_family' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE `email_log_eml` ADD CONSTRAINT `fk_eml_family` FOREIGN KEY (`eml_fam_ID`) REFERENCES `family_fam` (`fam_ID`) ON DELETE RESTRICT',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
-- END: email history follows its person and family
