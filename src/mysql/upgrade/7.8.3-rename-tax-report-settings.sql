-- Rename sTaxReport settings to Giving Statement names.
-- Copy each old value to the new key when the new key does not yet exist,
-- so churches keep their saved text. The old rows stay for downgrade safety.

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`)
SELECT 0, 'sGivingStatementOpening', `cfg_value`
FROM `config_cfg`
WHERE `cfg_name` = 'sTaxReport1'
  AND NOT EXISTS (SELECT 1 FROM `config_cfg` AS c2 WHERE c2.`cfg_name` = 'sGivingStatementOpening');

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`)
SELECT 0, 'sGivingStatementClosing', `cfg_value`
FROM `config_cfg`
WHERE `cfg_name` = 'sTaxReport2'
  AND NOT EXISTS (SELECT 1 FROM `config_cfg` AS c2 WHERE c2.`cfg_name` = 'sGivingStatementClosing');

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`)
SELECT 0, 'sGivingStatementQuestions', `cfg_value`
FROM `config_cfg`
WHERE `cfg_name` = 'sTaxReport3'
  AND NOT EXISTS (SELECT 1 FROM `config_cfg` AS c2 WHERE c2.`cfg_name` = 'sGivingStatementQuestions');

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`)
SELECT 0, 'sGivingStatementSigner', `cfg_value`
FROM `config_cfg`
WHERE `cfg_name` = 'sTaxSigner'
  AND NOT EXISTS (SELECT 1 FROM `config_cfg` AS c2 WHERE c2.`cfg_name` = 'sGivingStatementSigner');
