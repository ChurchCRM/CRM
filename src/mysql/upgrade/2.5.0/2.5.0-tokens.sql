DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL,
  `type` ENUM('verifyFamily', 'verifyPerson') NOT NULL,
  `reference_id` INT(9) NOT NULL,
  `valid_until_date` datetime NULL,
  `remainingUses` INT(2) NULL,
  PRIMARY KEY (`token`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;
