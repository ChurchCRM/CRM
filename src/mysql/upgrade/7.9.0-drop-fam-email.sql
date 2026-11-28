-- A family email written after the 7.8.3 move (member sign-in design 10.1, #10678).
-- The same rule runs again, then fam_Email is dropped. Statements that name the
-- column run only while it is still there, so resetting this file's checkpoint
-- and running it again is safe. family_email_move_fem stays: sign-in confirmation
-- and the "Family emails not moved" list still read it.
-- BEGIN: drop fam_Email after moving a family email written since 7.8.3

DELETE FROM `family_email_move_fem` WHERE `fem_Outcome` = 'pending';

SET @fem_run = NOW();
-- This run's rows are those inserted below; NOW() alone can repeat within a second.
SET @fem_max_id = IFNULL((SELECT MAX(`fem_ID`) FROM `family_email_move_fem`), 0);
SET @fem_head_roles = IFNULL((SELECT NULLIF(REPLACE(`cfg_value`, ' ', ''), '') FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleHead'), '1');
SET @fem_spouse_roles = IFNULL((SELECT NULLIF(REPLACE(`cfg_value`, ' ', ''), '') FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleSpouse'), '2');

SET @fem_has_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'family_fam' AND COLUMN_NAME = 'fam_Email'
);

SET @_sql = IF(
    @fem_has_col > 0,
    'INSERT INTO `family_email_move_fem` (`fem_fam_ID`, `fem_Email`, `fem_Outcome`, `fem_DateMoved`) SELECT `fam_ID`, TRIM(`fam_Email`), ''pending'', @fem_run FROM `family_fam` WHERE `fam_Email` IS NOT NULL AND TRIM(`fam_Email`) <> ''''',
    'DO 0'
);
PREPARE _fem FROM @_sql;
EXECUTE _fem;
DEALLOCATE PREPARE _fem;

SET @_sql = IF(@fem_has_col > 0, 'UPDATE `family_email_move_fem` m JOIN `family_fam` f ON f.`fam_ID` = m.`fem_fam_ID` SET m.`fem_Outcome` = ''held'',     m.`fem_per_ID` = (SELECT MIN(p.`per_ID`) FROM `person_per` p                       WHERE p.`per_fam_ID` = f.`fam_ID`                         AND LOWER(TRIM(p.`per_Email`)) = LOWER(TRIM(f.`fam_Email`))) WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = ''pending''   AND EXISTS (SELECT 1 FROM `person_per` p               WHERE p.`per_fam_ID` = f.`fam_ID`                 AND LOWER(TRIM(p.`per_Email`)) = LOWER(TRIM(f.`fam_Email`)))', 'DO 0');
PREPARE _fem FROM @_sql;
EXECUTE _fem;
DEALLOCATE PREPARE _fem;

-- Every personal email once, trimmed and in lower case, with the lowest per_ID
-- that has it, so each family email is one indexed lookup. Grouped in the key's
-- own collation, so the primary key never meets a duplicate.
DROP TEMPORARY TABLE IF EXISTS `fem_person_email`;
CREATE TEMPORARY TABLE `fem_person_email` (
  `fem_key`    varchar(100)          NOT NULL,
  `fem_per_ID` mediumint(9) unsigned NOT NULL,
  PRIMARY KEY (`fem_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fem_person_email` (`fem_key`, `fem_per_ID`) SELECT
  CONVERT(LOWER(TRIM(`per_Email`)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `fem_key`, MIN(`per_ID`)
FROM `person_per`
WHERE `per_Email` IS NOT NULL AND TRIM(`per_Email`) <> ''
GROUP BY `fem_key`;

UPDATE `family_email_move_fem` m
JOIN `fem_person_email` e ON e.`fem_key` = LOWER(m.`fem_Email`)
SET m.`fem_Outcome` = 'noted',
    m.`fem_Reason` = 'other_person',
    m.`fem_per_ID` = e.`fem_per_ID`
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'pending';

DROP TEMPORARY TABLE IF EXISTS `fem_person_email`;

-- Oldest first without window functions: the smallest birth-date key wins, unknown
-- parts sort last, and the key ends with the zero-padded per_ID it is read back from.
UPDATE `family_email_move_fem` m
JOIN (
  SELECT p.`per_fam_ID` AS fam_ID,
         CAST(SUBSTRING(MIN(CONCAT(
           LPAD(IFNULL(NULLIF(p.`per_BirthYear`, 0), 9999), 4, '0'),
           LPAD(IF(p.`per_BirthMonth` BETWEEN 1 AND 12, p.`per_BirthMonth`, 99), 2, '0'),
           LPAD(IF(p.`per_BirthDay` BETWEEN 1 AND 31, p.`per_BirthDay`, 99), 2, '0'),
           LPAD(p.`per_ID`, 10, '0'))), 9) AS UNSIGNED) AS per_ID
  FROM `person_per` p
  WHERE p.`per_DateDeceased` IS NULL AND p.`per_DateDeactivated` IS NULL
    AND (p.`per_Email` IS NULL OR TRIM(p.`per_Email`) = '')
    AND FIND_IN_SET(p.`per_fmr_ID`, @fem_head_roles) > 0
  GROUP BY p.`per_fam_ID`
) c ON c.fam_ID = m.`fem_fam_ID`
SET m.`fem_Outcome` = 'head', m.`fem_per_ID` = c.per_ID
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'pending';

UPDATE `family_email_move_fem` m
JOIN (
  SELECT p.`per_fam_ID` AS fam_ID,
         CAST(SUBSTRING(MIN(CONCAT(
           LPAD(IFNULL(NULLIF(p.`per_BirthYear`, 0), 9999), 4, '0'),
           LPAD(IF(p.`per_BirthMonth` BETWEEN 1 AND 12, p.`per_BirthMonth`, 99), 2, '0'),
           LPAD(IF(p.`per_BirthDay` BETWEEN 1 AND 31, p.`per_BirthDay`, 99), 2, '0'),
           LPAD(p.`per_ID`, 10, '0'))), 9) AS UNSIGNED) AS per_ID
  FROM `person_per` p
  WHERE p.`per_DateDeceased` IS NULL AND p.`per_DateDeactivated` IS NULL
    AND (p.`per_Email` IS NULL OR TRIM(p.`per_Email`) = '')
    AND FIND_IN_SET(p.`per_fmr_ID`, @fem_spouse_roles) > 0
  GROUP BY p.`per_fam_ID`
) c ON c.fam_ID = m.`fem_fam_ID`
SET m.`fem_Outcome` = 'spouse', m.`fem_per_ID` = c.per_ID
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'pending';

-- Two families with the same email: the lower family id takes it, so no two
-- people end up sharing a personal email; the other family gets a note.
DROP TEMPORARY TABLE IF EXISTS `fem_first_move`;
CREATE TEMPORARY TABLE `fem_first_move` (
  `fem_key`   varchar(100) NOT NULL,
  `fem_first` varchar(20)  NOT NULL,
  PRIMARY KEY (`fem_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fem_first_move` (`fem_key`, `fem_first`) SELECT
  LOWER(`fem_Email`), MIN(CONCAT(LPAD(`fem_fam_ID`, 10, '0'), LPAD(`fem_per_ID`, 10, '0')))
FROM `family_email_move_fem`
WHERE `fem_ID` > @fem_max_id AND `fem_Outcome` IN ('head', 'spouse')
GROUP BY LOWER(`fem_Email`);

UPDATE `family_email_move_fem` m
JOIN `fem_first_move` t ON t.`fem_key` = LOWER(m.`fem_Email`)
SET m.`fem_Outcome` = 'noted',
    m.`fem_Reason` = 'other_person',
    m.`fem_per_ID` = CAST(SUBSTRING(t.`fem_first`, 11) AS UNSIGNED)
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse')
  AND m.`fem_fam_ID` <> CAST(SUBSTRING(t.`fem_first`, 1, 10) AS UNSIGNED);

DROP TEMPORARY TABLE IF EXISTS `fem_first_move`;

UPDATE `family_email_move_fem` m
SET m.`fem_Outcome` = 'noted',
    m.`fem_Reason` = IF(EXISTS (SELECT 1 FROM `person_per` p
                                WHERE p.`per_fam_ID` = m.`fem_fam_ID`
                                  AND p.`per_DateDeceased` IS NULL AND p.`per_DateDeactivated` IS NULL
                                  AND (FIND_IN_SET(p.`per_fmr_ID`, @fem_head_roles) > 0
                                       OR FIND_IN_SET(p.`per_fmr_ID`, @fem_spouse_roles) > 0)),
                        'both_have_email', 'no_adult')
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'pending';

UPDATE `family_email_move_fem` m
SET m.`fem_NeedsConfirm` = IF((SELECT COUNT(*) FROM `person_per` p
                               WHERE p.`per_fam_ID` = m.`fem_fam_ID`
                                 AND p.`per_DateDeceased` IS NULL AND p.`per_DateDeactivated` IS NULL) > 1, 1, 0)
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse');

-- Each statement commits on its own, so the address is marked unconfirmed before
-- it is copied: an upgrade stopped between the two never leaves it usable to sign in.
INSERT INTO `person_signin_psi` (`psi_per_ID`, `psi_UnconfirmedEmail`, `psi_UnconfirmedSource`, `psi_UnconfirmedAt`) SELECT
  m.`fem_per_ID`, m.`fem_Email`, 'family_email', @fem_run
FROM `family_email_move_fem` m
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse') AND m.`fem_NeedsConfirm` = 1
ON DUPLICATE KEY UPDATE `psi_UnconfirmedEmail` = m.`fem_Email`, `psi_UnconfirmedSource` = 'family_email', `psi_UnconfirmedAt` = @fem_run;

UPDATE `person_per` p
JOIN `family_email_move_fem` m ON m.`fem_per_ID` = p.`per_ID`
SET p.`per_Email` = m.`fem_Email`
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse');

-- nte_EnteredBy -4 is Person::UPGRADE, shown as "System Upgrade" on the timeline.
INSERT INTO `note_nte` (`nte_per_ID`, `nte_fam_ID`, `nte_Private`, `nte_Text`, `nte_DateEntered`, `nte_EnteredBy`, `nte_EditedBy`, `nte_Type`) SELECT
       0, m.`fem_fam_ID`, 0,
       CONCAT('The family email ', m.`fem_Email`, ' was not moved to a person: ',
              CASE m.`fem_Reason`
                WHEN 'both_have_email' THEN 'the head of household and spouse both have their own email'
                WHEN 'no_adult' THEN 'the family has no living, active head of household or spouse'
                ELSE CONCAT('another person (', TRIM(CONCAT(IFNULL(p.`per_FirstName`, ''), ' ', IFNULL(p.`per_LastName`, ''))), ') already uses this address')
              END, '.'),
       @fem_run, -4, 0, 'family-email'
FROM `family_email_move_fem` m
LEFT JOIN `person_per` p ON p.`per_ID` = m.`fem_per_ID`
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'noted';


SET @_sql = IF(
    @fem_has_col > 0,
    'UPDATE `family_fam` SET `fam_Email` = NULL WHERE `fam_Email` IS NOT NULL',
    'DO 0'
);
PREPARE _fem FROM @_sql;
EXECUTE _fem;
DEALLOCATE PREPARE _fem;

SET @_sql = IF(
    @fem_has_col > 0,
    'ALTER TABLE `family_fam` DROP COLUMN `fam_Email`',
    'DO 0'
);
PREPARE _fem FROM @_sql;
EXECUTE _fem;
DEALLOCATE PREPARE _fem;

-- END: drop fam_Email after moving a family email written since 7.8.3
