-- #10561: a new installation starts on Volunteer Management V2. An upgraded
-- installation that never chose a version was on V1, the old default, and stays
-- there. cfg_name is the primary key, so INSERT IGNORE keeps a choice already made.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`) VALUES ('sVolunteerVersion', 'v1');

-- Churches that changed the reminder lead keep their alert lead; others get the 48-hour default.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iVolunteerGapAlertLeadHours', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iVolunteerReminderLeadHours';

ALTER TABLE `volunteer_notification_vntf`
  MODIFY COLUMN `vntf_Type` enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved','help_offer','pool_add') NOT NULL;
