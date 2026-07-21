-- pre-6.0.0-consolidated.sql
-- Consolidates all pre-6.0.0 migration steps into a single file.
--
-- SUPPORTED PATH: ChurchInfo 1.2.14 / 1.3.0 / 1.3.1 / 1.3.2 → ChurchCRM 6.0.0
-- in one step, via the "consolidated-pre-6.0.0" upgrade.json entry. The
-- built-in upgrader then continues unattended from 6.0.0 to the latest release.
--
-- OUT OF SCOPE: installs already at an intermediate 2.x/3.x/4.x/5.x version are
-- NOT matched by "consolidated-pre-6.0.0" and have no automated upgrade path in
-- this file — running the full merged script against a DB that already applied
-- some of these changes would error (referencing already-dropped columns) or
-- destroy data (unconditional DROP TABLE on tables that already hold real rows).
-- Those installs must be manually brought forward to an intermediate release
-- first; from there the built-in upgrader can take over.
--
-- COMPACTION (objects removed because they cancel out within this window):
--   • 4.2.0-perms.sql omitted: permissions/roles tables are dropped by 6.5.0 (IF EXISTS).
--   • church_location_person / church_location_role omitted (also dropped by 6.5.0 IF EXISTS).
--   • church_location CREATE + 3.0.0 RENAME compacted: CREATE locations directly;
--     DROP TABLE IF EXISTS church_location as a normaliser.
--   • events_event.event_grpid ADD omitted (transient, never read; DROP IF EXISTS kept below).
--   • 2.9.0 UPDATE menuconfig_mcf omitted (table is dropped in the 3.0.0 section).
--   • 4.1.0 DROP INDEX canvassdata_can omitted (table dropped in the 5.9.0 section).
--   • permissions row DELETEs from 5.1.0 / 5.9.0 omitted (table is omitted).
-- PRESERVED despite being transient:
--   • config_cfg cfg_type / cfg_data churn — ENUM-extending MODIFYs keep subsequent
--     INSERT statements valid in strict-mode MariaDB before 2.6.0 drops those columns.
--   • 3.0.0.php / 3.5.0.php converted to inline SQL (removes ORM model-drift risk).
--   • 2.9.0-InnoDB.php kept as PHP (dynamic per-table ENGINE=InnoDB loop).


-- ===== from 2.0.0.sql =====

ALTER TABLE version_ver ADD COLUMN IF NOT EXISTS `ver_update_start` datetime DEFAULT NULL;
ALTER TABLE version_ver ADD COLUMN IF NOT EXISTS `ver_update_end` datetime DEFAULT NULL;
ALTER TABLE version_ver DROP COLUMN IF EXISTS `ver_date`;

DELETE FROM config_cfg WHERE cfg_id IN (2, 4, 15, 17, 24, 32, 35, 999);

INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section, cfg_category)
VALUES
  (2, 'debug', '1', 'boolean', '1',
   'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchCRM, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.',
   'General', NULL),
  (15, 'sDisallowedPasswords', 'churchcrm,password,god,jesus,church,christian', 'text', 'churchcrm,password,god,jesus,church,christian', 'A comma-separated list of disallowed (too obvious) passwords.', 'General', NULL),
  (24, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchCRM. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General', NULL),
  (999, 'bRegistered', '0', 'boolean', '0',
   'ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General', NULL),
  (2000, 'mailChimpApiKey', '', 'text', '', 'see https://mailchimp.com/help/about-api-keys/', 'General', NULL),
  (1034, 'sChurchChkAcctNum', '111111111', 'text', '', 'Church Checking Account Number', 'ChurchInfoReport', NULL);

UPDATE user_usr SET usr_Style = 'skin-blue';


-- ===== from 2.1.0.sql =====

ALTER TABLE note_nte ADD COLUMN IF NOT EXISTS nte_Type VARCHAR(45) NOT NULL DEFAULT 'note' AFTER nte_EditedBy;

-- Back-fill create/edit timeline notes for existing persons and families.
INSERT IGNORE INTO note_nte
  (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
SELECT per_id, 0, 0, 'Original Entry', per_EnteredBy, per_DateEntered, 'create'
FROM person_per;

INSERT IGNORE INTO note_nte
  (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
SELECT per_id, 0, 0, 'Last Edit', per_EditedBy, per_DateLastEdited, 'edit'
FROM person_per
WHERE per_DateLastEdited IS NOT NULL;

INSERT IGNORE INTO note_nte
  (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
SELECT 0, fam_ID, 0, 'Original Entry', fam_EnteredBy, fam_DateEntered, 'create'
FROM family_fam;

INSERT IGNORE INTO note_nte
  (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
SELECT 0, fam_ID, 0, 'Last Edit', fam_EditedBy, fam_DateLastEdited, 'edit'
FROM family_fam
WHERE fam_DateLastEdited IS NOT NULL;

DELETE FROM config_cfg WHERE cfg_id IN (4);


-- ===== from 2.1.3.sql =====

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1035, 'sEnableGravatarPhotos', '1', 'boolean', '1', 'lookup user images on Gravatar when no local image is present', 'General', NULL);


-- ===== from 2.1.8.sql =====

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1036, 'sEnableExternalBackupTarget', '0', 'boolean', '0', 'Enable Remote Backups to Cloud Services', 'General', 'Step5'),
(1037, 'sExternalBackupType', 'WebDAV', 'Text', '', 'Cloud Service Type (Supported values: WebDAV, Local)', 'General', 'Step5'),
(1038, 'sExternalBackupEndpoint', '', 'Text', '', 'Remote Backup Endpoint', 'General', 'Step5'),
(1039, 'sExternalBackupUsername', '', 'Text', '', 'Remote Backup Username', 'General', 'Step5'),
(1040, 'sExternalBackupPassword', '', 'Text', '', 'Remote Backup Password', 'General', 'Step5'),
(1041, 'sExternalBackupAutoInterval', '', 'Text', '', 'Interval in Hours for Automatic Remote Backups', 'General', 'Step5'),
(1042, 'sLastBackupTimeStamp', '', 'Text', '', 'Last Backup Timestamp', 'General', 'Step5');


-- ===== from 2.2.0.sql =====

-- cfg_type ENUM extensions are kept: they allow subsequent INSERT statements with
-- cfg_type='json' to succeed on strict-mode MariaDB before 2.6.0 drops cfg_type.
ALTER TABLE config_cfg CHANGE cfg_type cfg_type ENUM('text','number','date','boolean','textarea','json');

SET @JSONV = '{"date1":{"x":"12","y":"42"},"date2X":"185","leftX":"64","topY":"7","perforationY":"97","amountOffsetX":"35","lineItemInterval":{"x":"49","y":"7"},"max":{"x":"200","y":"140"},"numberOfItems":{"x":"136","y":"68"},"subTotal":{"x":"197","y":"42"},"topTotal":{"x":"197","y":"68"},"titleX":"85"}';

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1043, 'sQBDTSettings', @JSONV, 'json', @JSONV, 'QuickBooks Deposit Ticket Settings', 'ChurchInfoReport', 'Step7');

DELETE FROM `config_cfg` WHERE cfg_id = 3;

ALTER TABLE group_grp MODIFY grp_hasSpecialProps INT(1);

UPDATE group_grp SET grp_hasSpecialProps = grp_hasSpecialProps = 1;

ALTER TABLE group_grp MODIFY grp_hasSpecialProps BOOLEAN;


-- ===== from 2.3.0.sql =====

ALTER TABLE `config_cfg`
MODIFY `cfg_type` ENUM('text','number','date','boolean','textarea','json','choice') NOT NULL default 'text';

ALTER TABLE `config_cfg` ADD COLUMN IF NOT EXISTS `cfg_data` text default NULL;

UPDATE `config_cfg` SET `cfg_data` = '{"Choices":["smtp","SendMail"]}',    `cfg_type` = 'choice' WHERE `cfg_id` = 25;
UPDATE `config_cfg` SET `cfg_data` = '{"Choices":["miles","kilometers"]}',  `cfg_type` = 'choice' WHERE `cfg_id` = 64;
UPDATE `config_cfg` SET `cfg_data` = '{"Choices":["Vanco","Authorize.NET"]}', `cfg_type` = 'choice' WHERE `cfg_id` = 73;
UPDATE `config_cfg` SET `cfg_data` = '{"Choices":["WebDAV","Local"]}',      `cfg_type` = 'choice' WHERE `cfg_id` = 1037;
UPDATE `config_cfg` SET
  `cfg_data` = '{"Choices":["en_US","de_DE","en_AU","en_GB","es_ES","fr_FR","hu_HU","it_IT","nb_NO","nl_NL","pl_PL","pt_BR","ro_RO","ru_RU","se_SE","sq_AL","sv_SE","vi_VN","zh_CN","zh_TW"]}',
  `cfg_type` = 'choice'
WHERE `cfg_id` = 39;

UPDATE `config_cfg` SET `cfg_value` = 0 WHERE `cfg_name` = 'bRegistered';

DELETE FROM config_cfg WHERE cfg_id IN (1, 18, 2001);

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(80,   'sEnableSelfRegistration',       '0',   'boolean', '0',   'Set true to enable family self registration.', 'General'),
(100,  'sPhoneFormat',            '(999) 999-9999',        'text', '(999) 999-9999',        '', 'General'),
(101,  'sPhoneFormatWithExt',     '(999) 999-9999 x99999', 'text', '(999) 999-9999 x99999', '', 'General'),
(102,  'sDateFormatLong',         'yyyy-mm-dd',             'text', 'yyyy-mm-dd',            '', 'General'),
(103,  'sDateFormatNoYear',       'DD/MM',                  'text', 'DD/MM',                 '', 'General'),
(104,  'sDateFormatShort',        'yy-mm-dd',               'text', 'yy-mm-dd',              '', 'General'),
(1044, 'sEnableIntegrityCheck',   '1',   'boolean', '1',   'Enable Integrity Check', 'General'),
(1045, 'sIntegrityCheckInterval', '168', 'Text',    '168', 'Interval in Hours for Integrity Check', 'General'),
(1046, 'sLastIntegrityCheckTimeStamp', '', 'Text',  '',    'Last Integrity Check Timestamp', 'General');

DELETE FROM config_cfg WHERE cfg_id IN (61, 62, 63);

ALTER TABLE `person_per` CHANGE COLUMN `per_EnteredBy` `per_EnteredBy` SMALLINT(5) NOT NULL DEFAULT '0';
ALTER TABLE `family_fam` CHANGE COLUMN `fam_EnteredBy` `fam_EnteredBy` SMALLINT(5) NOT NULL DEFAULT '0';
ALTER TABLE `note_nte`   CHANGE COLUMN `nte_EnteredBy` `nte_EnteredBy` MEDIUMINT(8) NOT NULL DEFAULT '0';


-- ===== from 2.4.0.sql =====

ALTER TABLE `config_cfg`
MODIFY `cfg_type` ENUM('text','number','date','boolean','textarea','json','choice', 'country') NOT NULL default 'text';

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(1047, 'sChurchCountry', 'United States', 'country', '', 'Church Country', 'ChurchInfoReport');

ALTER TABLE user_usr DROP COLUMN IF EXISTS `usr_BaseFontSize`;
ALTER TABLE user_usr DROP COLUMN IF EXISTS `usr_Communication`;
ALTER TABLE user_usr DROP COLUMN IF EXISTS `usr_Workspacewidth`;

ALTER TABLE `user_usr`
CHANGE COLUMN `usr_NeedPasswordChange` `usr_NeedPasswordChange` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
CHANGE COLUMN `usr_UserName`           `usr_UserName`           VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
CHANGE COLUMN `usr_AddRecords`         `usr_AddRecords`         TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_EditRecords`        `usr_EditRecords`        TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_DeleteRecords`      `usr_DeleteRecords`      TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_MenuOptions`        `usr_MenuOptions`        TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_EditSelf`           `usr_EditSelf`           TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_ManageGroups`       `usr_ManageGroups`       TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_Finance`            `usr_Finance`            TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
CHANGE COLUMN `usr_Admin`              `usr_Admin`              TINYINT(1) UNSIGNED NOT NULL DEFAULT '0';


-- ===== from 2.5.0.sql =====

DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL,
  `type` ENUM('verifyFamily', 'verifyPerson') NOT NULL,
  `reference_id` INT(9) NOT NULL,
  `valid_until_date` datetime NULL,
  `remainingUses` INT(2) NULL,
  PRIMARY KEY (`token`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(1048, 'sConfirmSincerely', 'Sincerely', 'Text', 'Sincerely', 'Used to end a letter before Signer', 'ChurchInfoReport'),
(1050, 'googleTrackingID',  '',          'Text', '',          'Google Analytics Tracking Code', 'General');

UPDATE config_cfg SET cfg_data = '{"Choices":["English - United States:en_US", "English - Canada:en_CA", "English - Australia:en_AU", "English - Great Britain:en_GB", "German - Germany:de_DE", "Spanish - Spain:es_ES", "French - France:fr_FR", "Hungarian:hu_HU", "Italian - Italy:it_IT", "Norwegian:nb_NO", "Dutch - Netherlands:nl_NL", "Polish:pl_PL", "Portuguese - Brazil:pt_BR", "Romanian - Romania:ro_RO", "Russian:ru_RU", "Sami (Northern) (Sweden):se_SE", "Albanian:sq_AL", "Swedish - Sweden:sv_SE", "Vietnamese:vi_VN", "Chinese - China:zh_CN", "Chinese - Taiwan:zh_TW"]}' WHERE cfg_id = 39;
UPDATE config_cfg SET cfg_tooltip = 'Internationalization (I18n) support' WHERE cfg_id = 39;
UPDATE config_cfg SET cfg_tooltip = 'Make user-entered zip/postcodes UPPERCASE when saving to the database.' WHERE cfg_id = 67;

INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
(1, 'SELECT CONCAT(''<a href=v2/family/'',fam_ID,''>'',fam_Name,''</a>'') AS ''Family Name''   FROM family_fam Where fam_WorkPhone != ""', 'Family Member Count', 'Returns each family and the total number of people assigned to them.', 0);

DELETE FROM config_cfg WHERE cfg_id IN (18, 2001);

UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id` IN (1011,1012,1013,1015,1017,1018,1019,1020,1021,1022,1023,1024,1026,1027,1028,1029,1031,1032,1033);


-- ===== from 2.6.0.sql =====

DELETE FROM config_cfg WHERE cfg_value = cfg_default;

ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_type`;
ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_tooltip`;
ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_section`;
ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_category`;
ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_data`;
ALTER TABLE config_cfg DROP COLUMN IF EXISTS `cfg_default`;


-- ===== from 2.7.0.sql =====

ALTER TABLE group_grp ADD COLUMN IF NOT EXISTS grp_active               BOOLEAN DEFAULT 1 NOT NULL AFTER grp_hasSpecialProps;
ALTER TABLE group_grp ADD COLUMN IF NOT EXISTS grp_include_email_export BOOLEAN DEFAULT 1 NOT NULL AFTER grp_active;

ALTER TABLE queryparameteroptions_qpo MODIFY qpo_Value VARCHAR(255) NOT NULL DEFAULT '';

UPDATE queryparameteroptions_qpo SET qpo_Value = 'CONCAT(COALESCE(`per_FirstName`,''),COALESCE(`per_MiddleName`,''),COALESCE(`per_LastName`,''))'
WHERE qpo_ID = 5;

UPDATE query_qry SET qry_SQL = 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',COALESCE(`per_FirstName`,''''),'' '',COALESCE(`per_MiddleName`,''''),'' '',COALESCE(`per_LastName`,''''),''</a>'') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE ''%~searchstring~%'''
WHERE qry_ID = 15;

DELETE FROM userconfig_ucfg WHERE ucfg_name IN ('sFromEmailAddress', 'sFromName', 'bSendPHPMail');

ALTER TABLE event_attend ADD COLUMN IF NOT EXISTS attend_id INT PRIMARY KEY AUTO_INCREMENT FIRST;


-- ===== from 2.8.0.sql =====

-- events_event.event_grpid ADD is omitted (transient: added here, dropped in the 3.0.0
-- section below, never read for any persistent side effect).
-- A DROP COLUMN IF EXISTS normaliser is kept in the 3.0.0 section to clean installs
-- that historically ran 2.8.0 before this consolidation existed.

DROP TABLE IF EXISTS `kioskdevice_kdev`;
CREATE TABLE `kioskdevice_kdev` (
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUIDHash` char(64) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` mediumint(9) NOT NULL DEFAULT 0,
  `kdev_lastHeartbeat` TIMESTAMP,
  `kdev_Accepted` BOOLEAN,
  `kdev_PendingCommands` varchar(50),
  PRIMARY KEY  (`kdev_ID`),
  UNIQUE KEY `kdev_ID` (`kdev_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `kioskassginment_kasm`;
CREATE TABLE `kioskassginment_kasm` (
  `kasm_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kasm_kdevId` mediumint(9) DEFAULT NULL,
  `kasm_AssignmentType` mediumint(9) DEFAULT NULL,
  `kasm_EventId` mediumint(9) DEFAULT 0,
  PRIMARY KEY  (`kasm_ID`),
  UNIQUE KEY `kasm_ID` (`kasm_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

ALTER TABLE event_types ADD COLUMN IF NOT EXISTS type_grpid mediumint(9) AFTER type_active;

ALTER TABLE `tokens` CHANGE COLUMN `type` `type` VARCHAR(50);

DELETE FROM config_cfg WHERE cfg_name = 'sToEmailAddress' AND cfg_value = 'myReceiveEmailAddress';

UPDATE config_cfg SET cfg_name = 'sGoogleTrackingID'          WHERE cfg_name = 'googleTrackingID';
UPDATE config_cfg SET cfg_name = 'sMailChimpApiKey'            WHERE cfg_name = 'mailChimpApiKey';
UPDATE config_cfg SET cfg_name = 'bEnableSelfRegistration'     WHERE cfg_name = 'sEnableSelfRegistration';
UPDATE config_cfg SET cfg_name = 'bForceUppercaseZip'          WHERE cfg_name = 'cfgForceUppercaseZip';
UPDATE config_cfg SET cfg_name = 'bDebug'                      WHERE cfg_name = 'debug';
UPDATE config_cfg SET cfg_name = 'bSMTPAuth'                   WHERE cfg_name = 'sSMTPAuth';
UPDATE config_cfg SET cfg_name = 'bEnableGravatarPhotos'       WHERE cfg_name = 'sEnableGravatarPhotos';
UPDATE config_cfg SET cfg_name = 'bEnableExternalBackupTarget' WHERE cfg_name = 'sEnableExternalBackupTarget';
UPDATE config_cfg SET cfg_name = 'bEnableIntegrityCheck'       WHERE cfg_name = 'sEnableIntegrityCheck';
UPDATE config_cfg SET cfg_name = 'iMinPasswordLength'          WHERE cfg_name = 'sMinPasswordLength';
UPDATE config_cfg SET cfg_name = 'iMinPasswordChange'          WHERE cfg_name = 'sMinPasswordChange';
UPDATE config_cfg SET cfg_name = 'iSessionTimeout'             WHERE cfg_name = 'sSessionTimeout';
UPDATE config_cfg SET cfg_name = 'iIntegrityCheckInterval'     WHERE cfg_name = 'sIntegrityCheckInterval';
UPDATE config_cfg SET cfg_name = 'iChurchLatitude'             WHERE cfg_name = 'nChurchLatitude';
UPDATE config_cfg SET cfg_name = 'iChurchLongitude'            WHERE cfg_name = 'nChurchLongitude';
UPDATE config_cfg SET cfg_name = 'aDisallowedPasswords'        WHERE cfg_name = 'sDisallowedPasswords';


-- ===== from 2.9.0.sql =====

ALTER TABLE person_per ADD COLUMN IF NOT EXISTS per_Twitter  varchar(50) default NULL AFTER per_Flags;
ALTER TABLE person_per ADD COLUMN IF NOT EXISTS per_LinkedIn varchar(50) default NULL AFTER per_Twitter;

ALTER TABLE person_custom_master ADD PRIMARY KEY (custom_Field);

-- UPDATE menuconfig_mcf is omitted (the table is dropped in the 3.0.0 section below).
-- church_location CREATE is omitted (compacted: CREATE locations directly below).
-- church_location_person / church_location_role CREATEs omitted (dropped by 6.5.0 IF EXISTS).


-- ===== from 2.10.0.sql =====

-- AFTER clause omitted: event_grpid is not created in this consolidated script.
ALTER TABLE events_event ADD COLUMN IF NOT EXISTS event_publicly_visible BOOLEAN DEFAULT FALSE;


-- ===== from 3.0.0.sql =====

ALTER TABLE events_event ADD COLUMN IF NOT EXISTS location_id                 INT DEFAULT NULL AFTER `event_typename`;
ALTER TABLE events_event ADD COLUMN IF NOT EXISTS primary_contact_person_id   INT DEFAULT NULL AFTER `location_id`;
ALTER TABLE events_event ADD COLUMN IF NOT EXISTS secondary_contact_person_id INT DEFAULT NULL AFTER `primary_contact_person_id`;
ALTER TABLE events_event ADD COLUMN IF NOT EXISTS event_url                   text DEFAULT NULL AFTER `secondary_contact_person_id`;

DROP TABLE IF EXISTS `event_audience`;
CREATE TABLE `event_audience` (
  `event_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`event_id`,`group_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `calendars`;
CREATE TABLE `calendars` (
  `calendar_id` INT NOT NULL auto_increment,
  `name` VARCHAR(128) NOT NULL,
  `accesstoken` VARCHAR(255),
  `foregroundColor` VARCHAR(6),
  `backgroundColor` VARCHAR(6),
  PRIMARY KEY (`calendar_id`),
  UNIQUE KEY `accesstoken` (`accesstoken`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `calendar_events`;
CREATE TABLE `calendar_events` (
  `calendar_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  PRIMARY KEY (`calendar_id`,`event_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Compacted from 2.9.0 CREATE TABLE church_location + 3.0.0 DROP/RENAME.
-- Net effect is identical: `locations` exists with these columns.
CREATE TABLE IF NOT EXISTS `locations` (
  `location_id`        INT NOT NULL,
  `location_typeId`    INT NOT NULL,
  `location_name`      VARCHAR(256) NOT NULL,
  `location_address`   VARCHAR(45) NOT NULL,
  `location_city`      VARCHAR(45) NOT NULL,
  `location_state`     VARCHAR(45) NOT NULL,
  `location_zip`       VARCHAR(45) NOT NULL,
  `location_country`   VARCHAR(45) NOT NULL,
  `location_phone`     VARCHAR(45) NULL,
  `location_email`     VARCHAR(45) NULL,
  `location_timzezone` VARCHAR(45) NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Normaliser: cleans up any legacy install that still has a church_location table.
DROP TABLE IF EXISTS `church_location`;

-- Split the original combined ALTER (ADD COLUMN + ADD UNIQUE INDEX) into two statements.
ALTER TABLE user_usr ADD COLUMN IF NOT EXISTS usr_apiKey VARCHAR(255) AFTER usr_UserName;
ALTER TABLE user_usr ADD UNIQUE INDEX IF NOT EXISTS `usr_apiKey_unique` (`usr_apiKey` ASC);

DROP TABLE IF EXISTS menuconfig_mcf;

DROP TABLE IF EXISTS `menu_links`;
CREATE TABLE `menu_links` (
  `linkId`    mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `linkName`  varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `linkUri`   text COLLATE utf8_unicode_ci NOT NULL,
  `linkOrder` INT NOT NULL,
  PRIMARY KEY (`linkId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- ===== converted from 3.0.0.php (inline SQL; removes ORM Calendar/EventQuery dependency) =====

-- Seed the two migration calendars.
INSERT IGNORE INTO calendars (name, foregroundColor, backgroundColor)
VALUES ('Public Calendar',  'FFFFFF', '00AA00');

INSERT IGNORE INTO calendars (name, foregroundColor, backgroundColor)
VALUES ('Private Calendar', 'FFFFFF', '0000AA');

-- Assign every event to the Public or Private calendar based on event_publicly_visible.
-- calendar_events has a composite PK (calendar_id, event_id) so INSERT IGNORE dedupes.
INSERT IGNORE INTO calendar_events (calendar_id, event_id)
SELECT (SELECT calendar_id FROM calendars WHERE name = 'Public Calendar'  LIMIT 1), event_id
FROM events_event WHERE COALESCE(event_publicly_visible, 0) = 1;

INSERT IGNORE INTO calendar_events (calendar_id, event_id)
SELECT (SELECT calendar_id FROM calendars WHERE name = 'Private Calendar' LIMIT 1), event_id
FROM events_event WHERE COALESCE(event_publicly_visible, 0) = 0;

-- Drop the transient column; it was only used for this one-time migration.
ALTER TABLE events_event DROP COLUMN IF EXISTS `event_publicly_visible`;

-- Normaliser: installs that historically ran 2.8.0 may still have event_grpid.
ALTER TABLE events_event DROP COLUMN IF EXISTS `event_grpid`;


-- ===== from 3.4.0.sql =====

DELETE FROM query_qry WHERE qry_ID = 1;

INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
  (201, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'',per_LastName,''</a>'') AS Name, per_LastName AS Lastname FROM person_per LEFT OUTER JOIN (SELECT event_attend.attend_id, event_attend.person_id FROM event_attend WHERE event_attend.event_id IN (~event~)) a ON person_per.per_ID = a.person_id WHERE a.attend_id is NULL ORDER BY person_per.per_LastName, person_per.per_FirstName', 'Missing people', 'Find people who didn''t attend an event', 1);

INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES
  (202, 201, 3, 'SELECT event_id as Value, event_title as Display FROM events_event ORDER BY event_start DESC', 'Event', 'Select the desired event', 'event', '', 1, 0, '', 0, 0, 0, 0);

UPDATE family_fam SET fam_State = 'NL' WHERE fam_State = 'NF';
UPDATE person_per  SET per_State = 'NL' WHERE per_State = 'NF';


-- ===== from 3.5.0.sql =====

DELETE FROM list_lst WHERE lst_OptionName = 'bCommunication';
DELETE FROM list_lst WHERE lst_OptionName = 'bMenuOptions';


-- ===== converted from 3.5.0.php (inline SQL; removes raw PHP dependency) =====

ALTER TABLE family_custom_master DROP COLUMN IF EXISTS `fam_custom_Side`;
ALTER TABLE person_custom_master DROP COLUMN IF EXISTS `custom_Side`;

-- custom_master may not exist at all in some ChurchInfo 1.x installs (original PHP used
-- try/catch for this reason).  Guard the entire ALTER with a table-existence check.
SET @_pre6_tbl = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_master'
);
SET @_pre6_sql = IF(@_pre6_tbl > 0, 'ALTER TABLE custom_master DROP COLUMN IF EXISTS `custom_Side`', 'DO 0');
PREPARE _pre6_stmt FROM @_pre6_sql;
EXECUTE _pre6_stmt;
DEALLOCATE PREPARE _pre6_stmt;


-- ===== from 4.0.0-TwoFactorAuth.sql =====

ALTER TABLE user_usr ADD COLUMN IF NOT EXISTS usr_TwoFactorAuthSecret          VARCHAR(255) NULL AFTER `usr_Canvasser`;
ALTER TABLE user_usr ADD COLUMN IF NOT EXISTS usr_TwoFactorAuthLastKeyTimestamp INT NULL AFTER `usr_TwoFactorAuthSecret`;
ALTER TABLE user_usr ADD COLUMN IF NOT EXISTS usr_TwoFactorAuthRecoveryCodes    TEXT NULL AFTER `usr_TwoFactorAuthLastKeyTimestamp`;


-- ===== from 4.1.0-cleanup.sql =====

DROP TABLE IF EXISTS autopayment_aut;

-- DROP INDEX for canvassdata_can is omitted: that table is dropped in the 5.9.0 section.
ALTER TABLE config_cfg DROP INDEX cfg_id;
ALTER TABLE donateditem_di DROP INDEX di_ID;
ALTER TABLE donationfund_fun DROP INDEX fun_ID;
ALTER TABLE family_fam DROP INDEX fam_ID;
ALTER TABLE fundraiser_fr DROP INDEX fr_ID;
ALTER TABLE group_grp DROP INDEX grp_ID_2;
ALTER TABLE group_grp DROP INDEX grp_ID;
ALTER TABLE multibuy_mb DROP INDEX mb_ID;
ALTER TABLE paddlenum_pn DROP INDEX pn_ID;
ALTER TABLE person_per DROP INDEX per_ID;
ALTER TABLE person2volunteeropp_p2vo DROP INDEX p2vo_ID;
ALTER TABLE property_pro DROP INDEX pro_ID_2;
ALTER TABLE property_pro DROP INDEX pro_ID;
ALTER TABLE propertytype_prt DROP INDEX prt_ID_2;
ALTER TABLE propertytype_prt DROP INDEX prt_ID;
ALTER TABLE query_qry DROP INDEX qry_ID_2;
ALTER TABLE query_qry DROP INDEX qry_ID;
ALTER TABLE queryparameteroptions_qpo DROP INDEX qpo_ID;
ALTER TABLE queryparameters_qrp DROP INDEX qrp_ID_2;
ALTER TABLE queryparameters_qrp DROP INDEX qrp_ID;
ALTER TABLE user_usr DROP INDEX usr_per_ID;
ALTER TABLE volunteeropportunity_vol DROP INDEX vol_ID;
ALTER TABLE kioskdevice_kdev DROP INDEX kdev_ID;

ALTER TABLE `locations` CHANGE `location_id` `location_id` INT(11) NOT NULL AUTO_INCREMENT;


-- 4.2.0-perms.sql omitted entirely.
-- permissions / person_permission / roles / person_roles are created in the original
-- but dropped by the untouched 6.5.0.sql (DROP TABLE IF EXISTS).  Creating them would
-- be a no-op for the net result and adds unnecessary churn for fresh installs.


-- ===== from 4.3.0-usersettings.sql =====

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `user_id`       int(11)     NOT NULL,
  `setting_name`  varchar(50) NOT NULL,
  `setting_value` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `user_settings` ADD PRIMARY KEY (`user_id`, `setting_name`);

INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'ui.style',                 usr_Style        FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'ui.table.size',            usr_SearchLimit  FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'ui.search.calendar.start', usr_CalStart     FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'ui.search.calendar.end',   usr_CalEnd       FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'finance.show.pledges',     usr_showPledges  FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'finance.show.payments',    usr_showPayments FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'finance.show.since',       usr_showSince    FROM user_usr;
INSERT IGNORE INTO user_settings SELECT usr_per_ID, 'finance.FY',               usr_defaultFY    FROM user_usr;
INSERT IGNORE INTO user_settings SELECT ucfg_per_id, 'ui.email.delimiter',      ucfg_value       FROM userconfig_ucfg WHERE ucfg_name = 'sMailtoDelimiter';


-- ===== from 4.4.0-FB.sql =====

ALTER TABLE person_per ADD COLUMN IF NOT EXISTS per_Facebook VARCHAR(50) NULL;


-- ===== from 5.1.0.sql =====

-- "DELETE FROM permissions ..." omitted: the permissions table is omitted (see above).
DELETE FROM config_cfg WHERE cfg_id IN (54, 55, 999);
DELETE FROM userconfig_ucfg WHERE ucfg_name = 'bUSAddressVerification';


-- ===== from 5.3.0.sql =====

INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES
        (28, '2016/2017', '21'), (28, '2017/2018', '22'), (28, '2018/2019', '23'),
        (28, '2019/2020', '24'), (28, '2020/2021', '25'), (28, '2021/2022', '26'), (28, '2022/2023', '27'),
        (30, '2016/2017', '21'), (30, '2017/2018', '22'), (30, '2018/2019', '23'),
        (30, '2019/2020', '24'), (30, '2020/2021', '25'), (30, '2021/2022', '26'), (30, '2022/2023', '27'),
        (31, '2016/2017', '21'), (31, '2017/2018', '22'), (31, '2018/2019', '23'),
        (31, '2019/2020', '24'), (31, '2020/2021', '25'), (31, '2021/2022', '26'), (31, '2022/2023', '27'),
        (32, '2016/2017', '21'), (32, '2017/2018', '22'), (32, '2018/2019', '23'),
        (32, '2019/2020', '24'), (32, '2020/2021', '25'), (32, '2021/2022', '26'), (32, '2022/2023', '27');


-- ===== from 5.3.1.sql =====

INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES
        (27, '2016/2017', '21'), (27, '2017/2018', '22'), (27, '2018/2019', '23'),
        (27, '2019/2020', '24'), (27, '2020/2021', '25'), (27, '2021/2022', '26'), (27, '2022/2023', '27');


-- ===== from 5.7.0.sql =====

ALTER TABLE person_per MODIFY per_BirthYear smallint(4) unsigned null;


-- ===== from 5.8.0.sql =====

ALTER TABLE events_event DROP COLUMN IF EXISTS `event_typename`;


-- ===== from 5.9.0.sql =====

DROP TABLE IF EXISTS canvassdata_can;
ALTER TABLE family_fam DROP COLUMN IF EXISTS fam_OkToCanvass;
ALTER TABLE family_fam DROP COLUMN IF EXISTS fam_Canvasser;
DELETE FROM list_lst WHERE lst_OptionName = 'bCanvasser';
DELETE FROM query_qry WHERE qry_ID = '27';
ALTER TABLE user_usr DROP COLUMN IF EXISTS usr_Canvasser;
-- "DELETE FROM permissions ..." omitted: table is omitted (see compaction notes).


-- ===== from 6.0.0.sql =====

-- Remove obsolete photo size configuration items
-- These were replaced with hardcoded values for optimal bandwidth/storage efficiency
-- The application never displays photos larger than 200px, so 400x400 storage was wasteful

-- iPhotoHeight (cfg_id 2034)
-- iPhotoWidth (cfg_id 2035)
-- iThumbnailWidth (cfg_id 2036)
-- iInitialsPointSize (cfg_id 2037)
-- iPhotoClientCacheDuration (cfg_id 2038) - Replaced with hardcoded 2-hour cache via Slim HttpCache middleware
-- bBackupExtraneousImages (cfg_id 2062) - Initials and remote images are never backed up (can be regenerated)
DELETE FROM config_cfg WHERE cfg_id IN (2034, 2035, 2036, 2037, 2038, 2062);
