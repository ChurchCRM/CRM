ALTER TABLE `config_cfg`
MODIFY `cfg_type` ENUM('text','number','date','boolean','textarea','json','choice', 'country') NOT NULL default 'text';

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(1047, 'sChurchCountry', 'United States', 'country', '', 'Church Country', 'ChurchInfoReport');

/* make drop column if exists procedure */
DROP PROCEDURE IF EXISTS DropColumnIfExists;
CREATE PROCEDURE DropColumnIfExists(IN tableName VARCHAR(255), IN columnName VARCHAR(255))
BEGIN
    DECLARE columnExists INT;

-- Check if the column exists
SELECT COUNT(*)
INTO columnExists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE table_name = tableName AND column_name = columnName;

-- Drop the column if it exists
IF columnExists > 0 THEN
        SET @dropColumnQuery = CONCAT('ALTER TABLE `', tableName, '` DROP COLUMN `', columnName, '`');
PREPARE stmt FROM @dropColumnQuery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
END IF;
END;

CALL DropColumnIfExists('user_usr', 'usr_BaseFontSize');
CALL DropColumnIfExists('user_usr', 'usr_Communication');
CALL DropColumnIfExists('user_usr', 'usr_Workspacewidth');

ALTER TABLE `user_usr`
CHANGE COLUMN `usr_NeedPasswordChange` `usr_NeedPasswordChange` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '' ,
CHANGE COLUMN `usr_UserName` `usr_UserName` VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL COMMENT '',
CHANGE COLUMN `usr_AddRecords` `usr_AddRecords` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_EditRecords` `usr_EditRecords` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_DeleteRecords` `usr_DeleteRecords` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_MenuOptions` `usr_MenuOptions` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_EditSelf` `usr_EditSelf` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_ManageGroups` `usr_ManageGroups` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_Finance` `usr_Finance` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ,
CHANGE COLUMN `usr_Admin` `usr_Admin` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '' ;

DROP PROCEDURE IF EXISTS DropColumnIfExists;
