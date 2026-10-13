-- ChurchCRM 7.9.0 upgrade script

-- #10526: unfilled-position alerts used the reminder lead time until they got a
-- setting of their own. A church that changed the reminder lead keeps the alerts
-- it had, including none at 0; one that never changed it has no stored row and
-- gets the new default, the same 48 hours. INSERT IGNORE keeps a value already set.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iVolunteerGapAlertLeadHours', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iVolunteerReminderLeadHours';
