-- This describe the calendar :

-- The hierarchy of the groups
ALTER TABLE group_grp ADD `group_parent_id` mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE group_grp
ADD CONSTRAINT fk_grp_parent_ID 
	FOREIGN KEY (group_parent_id) REFERENCES group_grp(grp_ID)
	ON DELETE SET NULL;

-- The managers who could modify the groups
CREATE TABLE group_manager (
    `grp_mngr_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `grp_mngr_person_ID` mediumint(9) unsigned NOT NULL,
    PRIMARY KEY(grp_mngr_id),
    CONSTRAINT fk_grp_mngr_id
	FOREIGN KEY (grp_mngr_person_ID) 
	REFERENCES person_per(per_ID)
	ON DELETE CASCADE	
)
ENGINE= InnoDB;

-- IMPORTANT : the calendar if is now to a person on not to a group ( for security reasons )
-- When a person is deleted the calendar is deleted too : ensure the the integrity of the DB.

CREATE TABLE calendar_type (
    `cal_type_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `cal_type_Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    PRIMARY KEY(cal_type_id)
)
ENGINE= InnoDB;

INSERT INTO `calendar_type`(`cal_type_Name`) VALUES ('MATERIAL RESERVATION');
INSERT INTO `calendar_type`(`cal_type_Name`) VALUES ('ROOM RESERVATION');
INSERT INTO `calendar_type`(`cal_type_Name`) VALUES ('VIDEO PROJECTOR RESERVATION');
INSERT INTO `calendar_type`(`cal_type_Name`) VALUES ('CALENDAR');

CREATE TABLE calendar (
    `cal_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `cal_person_ID` mediumint(9) unsigned NOT NULL,
    `cal_parent_ID` mediumint(9) unsigned DEFAULT NULL,
    `cal_Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `cal_creation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `cal_desc` TEXT COLLATE utf8_unicode_ci,
    `cal_color` VARCHAR(10) NOT NULL DEFAULT '#DC143C', 
    `cal_cal_type_id`  mediumint(9) unsigned NOT NULL,
    PRIMARY KEY(cal_id),
    CONSTRAINT fk_cal_ID
	FOREIGN KEY (cal_person_ID) 
	REFERENCES person_per(per_ID)
	ON DELETE CASCADE,
     CONSTRAINT fk_cal_parent_ID
	FOREIGN KEY (cal_parent_ID) 
	REFERENCES calendar(cal_id)
	ON DELETE SET NULL,
     CONSTRAINT fk_cal_cal_type_ID
	FOREIGN KEY (cal_cal_type_id) 
	REFERENCES calendar_type(cal_type_id)
	ON DELETE CASCADE
)
ENGINE= InnoDB;

-- A calendar can be share to another user nor to a group via inclusion
-- A user can customise the color nor the description
-- a user has rights on the calendar :
--   - 2 to write 4 for reading and 6 to read write 0 for nothing

CREATE TABLE calendar_share (
    `cal_share_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `per_share_id`mediumint(9) unsigned NOT NULL,
    `cal_share_color` VARCHAR(10) NOT NULL DEFAULT '#DC143C', 
    PRIMARY KEY(cal_share_id),
    `cal_share_right` tinyint(4) NOT NULL DEFAULT '6',
    CONSTRAINT fk_cal_share_ID
	FOREIGN KEY (per_share_id) 
	REFERENCES calendar(cal_id)
	ON DELETE CASCADE
)
ENGINE= InnoDB;


-- An event in now attached to a calendar and not to a group (for security reason)
-- So we have to redefine the way for each event to be attached to a calendar and no more to a group

-- When a calendar is destroyed, the event are deleted too.

ALTER TABLE events_event ADD event_cal_id mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE events_event
ADD CONSTRAINT fk_event_cal_id
 	FOREIGN KEY (event_cal_id) 
	REFERENCES calendar(cal_id)
	ON DELETE CASCADE;


-- Last the make classroom appeal
-- We create an presence moment

CREATE TABLE presence (
    `pr_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `date_attendance` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY(pr_id)
)
ENGINE= InnoDB;

-- We can add a person to the presence moment
-- This part is deleted when a person id deleted or a presence is deleted

CREATE TABLE person_presence (
    `per_pr_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `per_presence_id` mediumint(9) unsigned NOT NULL,
    `per_person_id` mediumint(9) unsigned NOT NULL,
    `is_present` BOOLEAN NOT NULL DEFAULT false,
    `delay` VARCHAR(40) NOT NULL DEFAULT '0',
    `date_attendance` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `description` TEXT COLLATE utf8_unicode_ci,
    PRIMARY KEY(per_pr_id),
    CONSTRAINT fk_person_presence_ID
	FOREIGN KEY (per_presence_id) 
	REFERENCES presence(pr_id)
	ON DELETE CASCADE,
    CONSTRAINT fk_person_ID
	FOREIGN KEY (per_person_id) 
	REFERENCES person_per(per_ID)
	ON DELETE CASCADE
)
ENGINE= InnoDB;