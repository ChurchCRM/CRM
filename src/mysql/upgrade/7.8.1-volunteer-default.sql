-- ChurchCRM 7.8.1 upgrade script

-- #10561: a new installation starts on Volunteer Management V2. An upgraded
-- installation that never chose a version was on V1, the old default, and stays
-- there. cfg_name is the primary key, so INSERT IGNORE keeps a choice already made.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`) VALUES ('sVolunteerVersion', 'v1');
