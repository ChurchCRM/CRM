

-- --NOTE-- removed in 4.4.0 so commenting out --
ALTER TABLE person_per ADD COLUMN per_Twitter varchar(50) default NULL AFTER per_Flags;
ALTER TABLE person_per ADD COLUMN per_LinkedIn varchar(50) default NULL AFTER per_Twitter;

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
  `role_title` INT NOT NULL,
  PRIMARY KEY (`location_id`, `role_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
