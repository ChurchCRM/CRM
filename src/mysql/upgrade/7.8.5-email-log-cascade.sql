-- #10485: a person's or family's email history goes away with them.
--
-- Email history (email_log_eml) used to block deleting a person or family.
-- It no longer does: the rows for a recipient person or family are removed by
-- the database when that person or family is deleted. This removes rows that
-- already point at a deleted person or family, aligns eml_per_ID and
-- eml_fam_ID with per_ID and fam_ID (mediumint(9) unsigned), and adds foreign
-- keys with ON DELETE CASCADE. The sender column (eml_usr_ID) is left alone, so
-- deleting a staff member never removes mail they sent to other people.
--
-- Safe to run again: the DELETEs find nothing, and each column change and
-- constraint is guarded with information_schema.

-- BEGIN: email history follows its person and family
DELETE e FROM `email_log_eml` e
LEFT JOIN `person_per` p ON p.`per_ID` = e.`eml_per_ID`
WHERE e.`eml_per_ID` IS NOT NULL AND p.`per_ID` IS NULL;

DELETE e FROM `email_log_eml` e
LEFT JOIN `family_fam` f ON f.`fam_ID` = e.`eml_fam_ID`
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

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_person' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE `email_log_eml` ADD CONSTRAINT `fk_eml_person` FOREIGN KEY (`eml_per_ID`) REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml'
       AND CONSTRAINT_NAME = 'fk_eml_family' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE `email_log_eml` ADD CONSTRAINT `fk_eml_family` FOREIGN KEY (`eml_fam_ID`) REFERENCES `family_fam` (`fam_ID`) ON DELETE CASCADE',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
-- END: email history follows its person and family
