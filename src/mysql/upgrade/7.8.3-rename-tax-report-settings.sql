-- Rename sTaxReport settings to Giving Statement names.
-- Copy each old value to the new key, then delete the old rows.

INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'sGivingStatementOpening', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sTaxReport1';

INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'sGivingStatementClosing', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sTaxReport2';

INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'sGivingStatementQuestions', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sTaxReport3';

INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'sGivingStatementSigner', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sTaxSigner';

DELETE FROM `config_cfg` WHERE `cfg_name` IN ('sTaxReport1', 'sTaxReport2', 'sTaxReport3', 'sTaxSigner');
