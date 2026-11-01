-- ChurchCRM 7.8.3 upgrade script

-- Donor tax years have their own start month. An existing church keeps the statement years it
-- has today: the new setting starts at its fiscal year month. A church on January needs no row.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iTaxYearStartMonth', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iFYMonth' AND `cfg_value` NOT IN ('', '1');
