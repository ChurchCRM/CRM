DELETE FROM config_cfg WHERE cfg_value = cfg_default;

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

CALL DropColumnIfExists('config_cfg', 'cfg_type');
CALL DropColumnIfExists('config_cfg', 'cfg_default');
CALL DropColumnIfExists('config_cfg', 'cfg_tooltip');
CALL DropColumnIfExists('config_cfg', 'cfg_section');
CALL DropColumnIfExists('config_cfg', 'cfg_category');
CALL DropColumnIfExists('config_cfg', 'cfg_order');
CALL DropColumnIfExists('config_cfg', 'cfg_data');

DROP PROCEDURE IF EXISTS DropColumnIfExists;
