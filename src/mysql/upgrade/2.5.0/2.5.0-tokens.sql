CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL,
  `type` ENUM('verify') NOT NULL,
  `reference_id` INT(9) NOT NULL,
  `valid_until_date` datetime NULL,
  `use_count` INT(2) NULL,
  PRIMARY KEY (`token`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;
