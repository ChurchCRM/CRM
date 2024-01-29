/* make add column if not exists procedure */
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
CREATE PROCEDURE AddColumnIfNotExists(IN tableName VARCHAR(255), IN columnName VARCHAR(255), IN columnDesc VARCHAR(255))
BEGIN
    DECLARE columnExists INT;

-- Check if the column exists
SELECT COUNT(*)
INTO columnExists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE table_name = tableName AND column_name = columnName;

-- Add the column if it doesn't exist
IF columnExists = 0 THEN
        SET @addColumnQuery = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDesc);
PREPARE stmt FROM @addColumnQuery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
END IF;
END;

CALL AddColumnIfNotExists('group_grp', 'grp_active', 'BOOLEAN NOT NULL DEFAULT 1 AFTER grp_hasSpecialProps');
CALL AddColumnIfNotExists('group_grp', 'grp_include_email_export', 'BOOLEAN NOT NULL DEFAULT 1 AFTER grp_active');

ALTER TABLE queryparameteroptions_qpo
  MODIFY qpo_Value VARCHAR(255) NOT NULL DEFAULT '';

UPDATE queryparameteroptions_qpo SET qpo_Value = 'CONCAT(COALESCE(`per_FirstName`,''),COALESCE(`per_MiddleName`,''),COALESCE(`per_LastName`,''))'
WHERE qpo_ID = 5;

UPDATE query_qry SET qry_SQL = 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',COALESCE(`per_FirstName`,''''),'' '',COALESCE(`per_MiddleName`,''''),'' '',COALESCE(`per_LastName`,''''),''</a>'') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE ''%~searchstring~%'''
WHERE qry_ID = 15;

DELETE FROM userconfig_ucfg where ucfg_name = "sFromEmailAddress";
DELETE FROM userconfig_ucfg where ucfg_name = "sFromName";
DELETE FROM userconfig_ucfg where ucfg_name = "bSendPHPMail";

CALL AddColumnIfNotExists('event_attend', 'attend_id', 'INT PRIMARY KEY AUTO_INCREMENT FIRST');

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
