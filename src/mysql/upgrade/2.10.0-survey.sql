CREATE TABLE `survey_definitions` (
  `survey_definition_id` INT NOT NULL,
  `name` VARCHAR(256) NOT NULL,
  `definition` text,
  `owner_per_id` mediumint(9) unsigned NOT NULL,
  PRIMARY KEY (`survey_definition_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `survey_responses` (
  `survey_response_id` INT NOT NULL,
  `survey_definition_id` INT NOT NULL,
  `response` text,
  PRIMARY KEY (`survey_definition_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

ALTER TABLE token 
 ADD COLUMN `meta_data` text AFTER `remainingUses`,

