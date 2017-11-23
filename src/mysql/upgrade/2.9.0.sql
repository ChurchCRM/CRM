ALTER TABLE person_per
  ADD COLUMN per_FacebookID bigint(20) unsigned default NULL AFTER per_Flags;

ALTER TABLE person_per
  ADD COLUMN per_Twitter varchar(50) default NULL AFTER per_FacebookID;

ALTER TABLE person_per
  ADD COLUMN per_LinkedIn varchar(50) default NULL AFTER per_Twitter;
  
-- person calendar setup
CREATE TABLE person_calendar (
    `per_cal_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `person_ID` mediumint(9) unsigned NOT NULL,
    `per_cal_Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `per_cal_creation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `per_cal_desc` TEXT COLLATE utf8_unicode_ci,
    `per_cal_color` VARCHAR(10) NOT NULL DEFAULT '#DC143C', 
    PRIMARY KEY(per_cal_id),
    CONSTRAINT fk_per_cal_ID
    FOREIGN KEY (person_ID) 
    REFERENCES person_per(per_ID)
    ON DELETE CASCADE
)
ENGINE= InnoDB;


-- group calendar setup
CREATE TABLE group_calendar_persons (
    `grp_cal_id` mediumint(9) unsigned  NOT NULL AUTO_INCREMENT,
    `group_ID` mediumint(9) unsigned NOT NULL,
    `person_ID` mediumint(9) unsigned DEFAULT NULL,
    `grp_cal_creation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `grp_cal_desc` TEXT COLLATE utf8_unicode_ci,
    `grp_cal_color` VARCHAR(10) NOT NULL DEFAULT '#8A2BE2', 
    `grp_cal_right` tinyint(4) NOT NULL DEFAULT '6',
    PRIMARY KEY(grp_cal_id),
    CONSTRAINT fk_grp_cal_ID
    FOREIGN KEY (group_ID) 
    REFERENCES group_grp(grp_ID)
    ON DELETE CASCADE,
    CONSTRAINT fk_grp_per_cal_ID
        FOREIGN KEY (person_ID)
        REFERENCES person_per(per_ID)
        ON DELETE SET NULL
)
ENGINE= InnoDB;

-- alteration of the groups
ALTER TABLE group_grp ADD `group_manager_id` mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE group_grp
ADD CONSTRAINT fk_manager_ID 
    FOREIGN KEY (group_manager_id) REFERENCES person_per(per_ID)
    ON DELETE SET NULL;

ALTER TABLE group_grp ADD `group_parent_id` mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE group_grp
ADD CONSTRAINT fk_grp_parent_ID 
    FOREIGN KEY (group_parent_id) REFERENCES group_grp(grp_ID)
    ON DELETE SET NULL;

-- alteration of the events
ALTER TABLE events_event ADD event_grp_cal_id mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE events_event
ADD CONSTRAINT fk_event_grp_cal_id
     FOREIGN KEY (event_grp_cal_id) 
    REFERENCES group_calendar_persons(grp_cal_id)
    ON DELETE SET NULL;

ALTER TABLE events_event ADD event_per_cal_id mediumint(9) unsigned DEFAULT NULL;
ALTER TABLE events_event
ADD CONSTRAINT fk_event_per_cal_ID
    FOREIGN KEY (event_per_cal_id) 
    REFERENCES person_calendar(per_cal_id)
    ON DELETE CASCADE;
