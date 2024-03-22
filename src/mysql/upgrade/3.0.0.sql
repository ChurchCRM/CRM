ALTER TABLE events_event ADD COLUMN location_id INT DEFAULT NULL AFTER `event_typename`;
ALTER TABLE events_event ADD COLUMN primary_contact_person_id INT DEFAULT NULL AFTER `location_id`;
ALTER TABLE events_event ADD COLUMN secondary_contact_person_id INT DEFAULT NULL AFTER `primary_contact_person_id`;
ALTER TABLE events_event ADD COLUMN event_url text DEFAULT NULL AFTER `secondary_contact_person_id`;

DROP TABLE IF EXISTS `event_audience`;
# This is a join-table to link an event with a prospective audience for the purpose of advertising / outreach.
CREATE TABLE `event_audience` (
  `event_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`event_id`,`group_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `calendars`;
CREATE TABLE `calendars` (
  `calendar_id` INT NOT NULL auto_increment,
  `name` VARCHAR(128) NOT NULL,
  `accesstoken` VARCHAR(255),
  `foregroundColor` VARCHAR(6),
  `backgroundColor` VARCHAR(6),
  PRIMARY KEY (`calendar_id`),
  UNIQUE KEY `accesstoken` (`accesstoken`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;


DROP TABLE IF EXISTS `calendar_events`;
# This is a join-table to link an event with a calendar
CREATE TABLE `calendar_events` (
  `calendar_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  PRIMARY KEY (`calendar_id`,`event_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `locations`;
RENAME TABLE church_location TO locations;

ALTER TABLE user_usr
  ADD COLUMN usr_apiKey VARCHAR(255) AFTER usr_UserName,
	ADD UNIQUE INDEX `usr_apiKey_unique` (`usr_apiKey` ASC);

DROP TABLE IF EXISTS menuconfig_mcf;

DROP TABLE IF EXISTS `menu_links`;
CREATE TABLE `menu_links` (
  `linkId` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `linkName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `linkUri` text COLLATE utf8_unicode_ci NOT NULL,
  `linkOrder` INT NOT NULL,
  PRIMARY KEY (`linkId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
