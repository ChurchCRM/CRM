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

CALL AddColumnIfNotExists('person_per', 'per_FacebookID', 'bigint(20) unsigned default NULL AFTER per_Flags');
CALL AddColumnIfNotExists('person_per', 'per_Twitter', 'varchar(50) default NULL AFTER per_FacebookID');
CALL AddColumnIfNotExists('person_per', 'per_LinkedIn', 'varchar(50) default NULL AFTER per_Twitter');

ALTER TABLE person_custom_master
  ADD PRIMARY KEY (custom_Field);

UPDATE menuconfig_mcf
  SET security_grp = 'bAddEvent'
  WHERE name = 'addevent';

DROP TABLE IF EXISTS `church_location`;
CREATE TABLE `church_location` (
  `location_id` INT NOT NULL,
  `location_typeId` INT NOT NULL,
  `location_name` VARCHAR(256) NOT NULL,
  `location_address` VARCHAR(45) NOT NULL,
  `location_city` VARCHAR(45) NOT NULL,
  `location_state` VARCHAR(45) NOT NULL,
  `location_zip` VARCHAR(45) NOT NULL,
  `location_country` VARCHAR(45) NOT NULL,
  `location_phone` VARCHAR(45) NULL,
  `location_email` VARCHAR(45) NULL,
  `location_timzezone` VARCHAR(45) NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `church_location_person`;
CREATE TABLE `church_location_person` (
  `location_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `order` INT NOT NULL,
  `person_location_role_id` INT NOT NULL,  #This will be referenced to user-defined roles such as clergey, pastor, member, etc for non-denominational use
  PRIMARY KEY (`location_id`, `person_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `church_location_role`;
CREATE TABLE `church_location_role` (
  `location_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  `role_order` INT NOT NULL,
  `role_title` INT NOT NULL,  #Thi
  PRIMARY KEY (`location_id`, `role_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
