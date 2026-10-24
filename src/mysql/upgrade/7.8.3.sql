-- ChurchCRM 7.8.3 upgrade script

-- Member sign-in row 0 (#10197): counters for login throttling and temporary lockouts.
CREATE TABLE IF NOT EXISTS `rate_limit_rlm` (
  `rlm_ID`      int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rlm_Bucket`  varchar(64)      NOT NULL,
  `rlm_KeyHash` char(64)         NOT NULL,
  `rlm_Hits`    int(10) unsigned NOT NULL DEFAULT 0,
  `rlm_ResetAt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rlm_ID`),
  UNIQUE KEY `rlm_bucket_key_uidx` (`rlm_Bucket`, `rlm_KeyHash`),
  KEY `rlm_reset_at_idx` (`rlm_ResetAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member sign-in, row 1 (#10463): the person-keyed tables for the whole epic
-- (design docs/member-sign-in-design.md §13). Nothing is copied into them here;
-- the release-day cutover (row 13) moves the portal data from user_usr.

CREATE TABLE IF NOT EXISTS `person_signin_psi` (
  `psi_per_ID`            mediumint(9) unsigned NOT NULL,
  `psi_SessionVersion`    int(11)               NOT NULL DEFAULT 0,
  `psi_SignInDisabled`    tinyint(1) unsigned   NOT NULL DEFAULT 0,
  `psi_DisabledBy_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `psi_DisabledAt`        datetime                       DEFAULT NULL,
  `psi_DisabledReason`    varchar(255)                   DEFAULT NULL,
  `psi_ProvenEmail`       varchar(255)                   DEFAULT NULL,
  `psi_ProvenAt`          datetime                       DEFAULT NULL,
  `psi_UnconfirmedEmail`  varchar(255)                   DEFAULT NULL,
  `psi_UnconfirmedSource` varchar(32)                    DEFAULT NULL,
  `psi_UnconfirmedAt`     datetime                       DEFAULT NULL,
  `psi_FirstSignIn`       datetime                       DEFAULT NULL,
  `psi_LastSignIn`        datetime                       DEFAULT NULL,
  `psi_LastMethod`        varchar(20)                    DEFAULT NULL,
  `psi_LastActivity`      datetime                       DEFAULT NULL,
  `psi_CalendarToken`     varchar(64)                    DEFAULT NULL,
  `psi_CalendarSelection` text                           DEFAULT NULL,
  PRIMARY KEY (`psi_per_ID`),
  UNIQUE KEY `psi_calendar_token_uidx` (`psi_CalendarToken`),
  KEY `psi_last_signin_idx`            (`psi_LastSignIn`),
  CONSTRAINT `fk_psi_person` FOREIGN KEY (`psi_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_signin_request_psr` (
  `psr_ID`          int(11)               NOT NULL AUTO_INCREMENT,
  `psr_per_ID`      mediumint(9) unsigned NOT NULL,
  `psr_Email`       varchar(255)          NOT NULL,
  `psr_TokenHash`   char(64)              NOT NULL,
  `psr_CodeHash`    char(64)              NOT NULL,
  `psr_BrowserHash` char(64)              NOT NULL,
  `psr_Attempts`    int(11)               NOT NULL DEFAULT 0,
  `psr_CreatedAt`   datetime              NOT NULL,
  `psr_ExpiresAt`   datetime              NOT NULL,
  `psr_UsedAt`      datetime                       DEFAULT NULL,
  `psr_CancelledAt` datetime                       DEFAULT NULL,
  PRIMARY KEY (`psr_ID`),
  UNIQUE KEY `psr_token_uidx` (`psr_TokenHash`),
  KEY `psr_person_idx`        (`psr_per_ID`),
  KEY `psr_expires_idx`       (`psr_ExpiresAt`),
  CONSTRAINT `fk_psr_person` FOREIGN KEY (`psr_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_device_pdv` (
  `pdv_ID`         int(11)                 NOT NULL AUTO_INCREMENT,
  `pdv_per_ID`     mediumint(9) unsigned   NOT NULL,
  `pdv_TokenHash`  char(64)                NOT NULL,
  `pdv_Kind`       enum('browser','app')   NOT NULL DEFAULT 'browser',
  `pdv_Label`      varchar(255)                     DEFAULT NULL,
  `pdv_CreatedAt`  datetime                NOT NULL,
  `pdv_LastUsedAt` datetime                         DEFAULT NULL,
  `pdv_ExpiresAt`  datetime                NOT NULL,
  `pdv_RevokedAt`  datetime                         DEFAULT NULL,
  PRIMARY KEY (`pdv_ID`),
  UNIQUE KEY `pdv_token_uidx` (`pdv_TokenHash`),
  KEY `pdv_person_idx`        (`pdv_per_ID`),
  CONSTRAINT `fk_pdv_person` FOREIGN KEY (`pdv_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_passkey_ppk` (
  `ppk_ID`             int(11)               NOT NULL AUTO_INCREMENT,
  `ppk_per_ID`         mediumint(9) unsigned NOT NULL,
  `ppk_CredentialId`   text                  NOT NULL,
  `ppk_CredentialHash` char(64)              NOT NULL,
  `ppk_PublicKey`      text                  NOT NULL,
  `ppk_SignCount`      bigint(20)            NOT NULL DEFAULT 0,
  `ppk_UserHandle`     varchar(128)                   DEFAULT NULL,
  `ppk_Transports`     varchar(255)                   DEFAULT NULL,
  `ppk_Label`          varchar(255)                   DEFAULT NULL,
  `ppk_CreatedAt`      datetime              NOT NULL,
  `ppk_LastUsedAt`     datetime                       DEFAULT NULL,
  PRIMARY KEY (`ppk_ID`),
  UNIQUE KEY `ppk_credential_uidx` (`ppk_CredentialHash`),
  KEY `ppk_person_idx`             (`ppk_per_ID`),
  CONSTRAINT `fk_ppk_person` FOREIGN KEY (`ppk_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_grant_pgr` (
  `pgr_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `pgr_per_ID`           mediumint(9) unsigned NOT NULL,
  `pgr_Capability`       varchar(128)          NOT NULL,
  `pgr_ScopeType`        varchar(32)           NOT NULL DEFAULT 'none',
  `pgr_ScopeId`          int(11)               NOT NULL DEFAULT 0,
  `pgr_EndsAt`           datetime                       DEFAULT NULL,
  `pgr_Reason`           varchar(255)                   DEFAULT NULL,
  `pgr_GrantedBy_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `pgr_GrantedAt`        datetime              NOT NULL,
  PRIMARY KEY (`pgr_ID`),
  UNIQUE KEY `pgr_person_capability_scope_uidx` (`pgr_per_ID`, `pgr_Capability`, `pgr_ScopeType`, `pgr_ScopeId`),
  KEY `pgr_capability_idx`                      (`pgr_Capability`),
  CONSTRAINT `fk_pgr_person` FOREIGN KEY (`pgr_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_signin_audit_psa` (
  `psa_ID`           int(11)               NOT NULL AUTO_INCREMENT,
  `psa_Time`         datetime              NOT NULL,
  `psa_per_ID`       mediumint(9) unsigned          DEFAULT NULL,
  `psa_Event`        varchar(32)           NOT NULL,
  `psa_Method`       varchar(20)                    DEFAULT NULL,
  `psa_Reason`       varchar(64)                    DEFAULT NULL,
  `psa_Actor_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `psa_Email`        varchar(255)                   DEFAULT NULL,
  `psa_IP`           varchar(45)                    DEFAULT NULL,
  `psa_Detail`       varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`psa_ID`),
  KEY `psa_person_time_idx` (`psa_per_ID`, `psa_Time`),
  KEY `psa_time_idx`        (`psa_Time`),
  KEY `psa_email_time_idx`  (`psa_Email`, `psa_Time`),
  KEY `psa_ip_time_idx`     (`psa_IP`, `psa_Time`),
  CONSTRAINT `fk_psa_person` FOREIGN KEY (`psa_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member sign-in, row 1 (#10463): the member rule starts with the church's own
-- head-of-household and spouse roles ticked (design §4), whatever their ids are.
SET @msi_head = (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleHead');
SET @msi_spouse = (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleSpouse');
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
VALUES ('sMemberSignInFamilyRoles', CONCAT(COALESCE(NULLIF(@msi_head, ''), '1'), ',', COALESCE(NULLIF(@msi_spouse, ''), '2')));

-- ============================================================================
-- BEGIN: member sign-in row 11, family email moves to people (design 10.1)
-- ============================================================================
-- Each non-empty family email (compared trimmed and in lower case) goes to:
--   1. nobody, when a person already has it as their personal email
--      (a family note when that person is in another family);
--   2. else the oldest living, active head of household without a personal email;
--   3. else the oldest living, active spouse without a personal email;
--   4. else nobody, with a family note giving the reason.
-- Then the family email is cleared. family_email_move_fem keeps one row per
-- family moved: the outcome counts and the one-time "Family emails not moved"
-- list. An address moved into a family of two or more living, active people
-- (fem_NeedsConfirm = 1) is also put on person_signin_psi as an unconfirmed
-- email (source family_email): it may not be used to sign in until staff
-- confirm it (design 9). Safe to run again: only family emails still set move.

ALTER TABLE `person_per` MODIFY `per_Email` varchar(100) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `family_email_move_fem` (
  `fem_ID`           int(10) unsigned      NOT NULL AUTO_INCREMENT,
  `fem_fam_ID`       mediumint(9) unsigned NOT NULL,
  `fem_Email`        varchar(100)          NOT NULL,
  `fem_Outcome`      enum('pending','head','spouse','held','noted') NOT NULL DEFAULT 'pending',
  `fem_Reason`       enum('both_have_email','no_adult','other_person') DEFAULT NULL,
  `fem_per_ID`       mediumint(9) unsigned DEFAULT NULL,
  `fem_NeedsConfirm` tinyint(1) unsigned   NOT NULL DEFAULT 0,
  `fem_DateMoved`    datetime              NOT NULL,
  PRIMARY KEY (`fem_ID`),
  KEY `fem_fam_idx` (`fem_fam_ID`),
  KEY `fem_per_idx` (`fem_per_ID`),
  KEY `fem_outcome_idx` (`fem_Outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `family_email_move_fem` WHERE `fem_Outcome` = 'pending';

SET @fem_run = NOW();
-- This run's rows are those inserted below; NOW() alone can repeat within a second.
SET @fem_max_id = IFNULL((SELECT MAX(`fem_ID`) FROM `family_email_move_fem`), 0);
SET @fem_head_roles = IFNULL((SELECT NULLIF(REPLACE(`cfg_value`, ' ', ''), '') FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleHead'), '1');
SET @fem_spouse_roles = IFNULL((SELECT NULLIF(REPLACE(`cfg_value`, ' ', ''), '') FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleSpouse'), '2');

INSERT INTO `family_email_move_fem` (`fem_fam_ID`, `fem_Email`, `fem_Outcome`, `fem_DateMoved`) SELECT
  `fam_ID`, TRIM(`fam_Email`), 'pending', @fem_run
FROM `family_fam`
WHERE `fam_Email` IS NOT NULL AND TRIM(`fam_Email`) <> '';

UPDATE `family_email_move_fem` m
JOIN `family_fam` f ON f.`fam_ID` = m.`fem_fam_ID`
SET m.`fem_Outcome` = 'held',
    m.`fem_per_ID` = (SELECT MIN(p.`per_ID`) FROM `person_per` p
                      WHERE p.`per_fam_ID` = f.`fam_ID`
                        AND LOWER(TRIM(p.`per_Email`)) = LOWER(TRIM(f.`fam_Email`)))
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` = 'pending'
  AND EXISTS (SELECT 1 FROM `person_per` p
              WHERE p.`per_fam_ID` = f.`fam_ID`
                AND LOWER(TRIM(p.`per_Email`)) = LOWER(TRIM(f.`fam_Email`)));

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

UPDATE `person_per` p
JOIN `family_email_move_fem` m ON m.`fem_per_ID` = p.`per_ID`
SET p.`per_Email` = m.`fem_Email`
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse');

INSERT INTO `person_signin_psi` (`psi_per_ID`, `psi_UnconfirmedEmail`, `psi_UnconfirmedSource`, `psi_UnconfirmedAt`) SELECT
  m.`fem_per_ID`, m.`fem_Email`, 'family_email', @fem_run
FROM `family_email_move_fem` m
WHERE m.`fem_ID` > @fem_max_id AND m.`fem_Outcome` IN ('head', 'spouse') AND m.`fem_NeedsConfirm` = 1
ON DUPLICATE KEY UPDATE `psi_UnconfirmedEmail` = m.`fem_Email`, `psi_UnconfirmedSource` = 'family_email', `psi_UnconfirmedAt` = @fem_run;

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

UPDATE `family_fam` SET `fam_Email` = NULL WHERE `fam_Email` IS NOT NULL;

-- END: member sign-in row 11, family email moves to people
