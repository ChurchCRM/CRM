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

CALL AddColumnIfNotExists('user_usr', 'usr_TwoFactorAuthSecret', 'VARCHAR(255) NULL AFTER `usr_Canvasser`');
CALL AddColumnIfNotExists('user_usr', 'usr_TwoFactorAuthLastKeyTimestamp', 'INT NULL AFTER `usr_TwoFactorAuthSecret`');
CALL AddColumnIfNotExists('user_usr', 'usr_TwoFactorAuthRecoveryCodes', 'TEXT NULL AFTER `usr_TwoFactorAuthLastKeyTimestamp');

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
