CREATE TABLE `church_division` (
  `division_id` INT NOT NULL,
  `division_typeId` INT NOT NULL,
  `division_name` VARCHAR(256) NOT NULL,
  `division_address` VARCHAR(45) NOT NULL,
  `division_city` VARCHAR(45) NOT NULL,
  `division_state` VARCHAR(45) NOT NULL,
  `division_zip` VARCHAR(45) NOT NULL,
  `division_country` VARCHAR(45) NOT NULL,
  `division_phone` VARCHAR(45) NULL,
  `division_email` VARCHAR(45) NULL,
  `division_timzezone` VARCHAR(45) NULL,
  PRIMARY KEY (`division_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `church_clergy_clergy` (
  `division_id` INT NOT NULL,
  `clergy_personId` INT NOT NULL,
  `clergy_roleId` INT NOT NULL,
  PRIMARY KEY (`division_id`, `clergy_personId`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

