-- ChurchCRM 7.9.0 upgrade script

-- #10526: unfilled-position alerts used the reminder lead time until they got a
-- setting of their own. A church that changed the reminder lead keeps the alerts
-- it had, including none at 0; one that never changed it has no stored row and
-- gets the new default, the same 48 hours. INSERT IGNORE keeps a value already set.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iVolunteerGapAlertLeadHours', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iVolunteerReminderLeadHours';

-- #10531: a team leader adds someone to a ministry's volunteer pool and the
-- ministry's coordinators are emailed (outbox type `pool_add`). Appending an
-- ENUM value leaves every existing row as it is.
ALTER TABLE `volunteer_notification_vntf`
  MODIFY COLUMN `vntf_Type` enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved','help_offer','pool_add') NOT NULL;
