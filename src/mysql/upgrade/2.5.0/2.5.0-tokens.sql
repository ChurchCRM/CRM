CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL COMMENT '',
  `type` ENUM('verify') NOT NULL COMMENT '',
  `reference_id` INT(9) NOT NULL COMMENT '',
  `valid_until_date` TIMESTAMP NULL COMMENT '',
  `use_count` INT(2) NULL COMMENT '',
  PRIMARY KEY (`token`)  COMMENT '');
