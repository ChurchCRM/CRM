ALTER TABLE `config_cfg`
MODIFY `cfg_type` ENUM('text','number','date','boolean','textarea','json','choice') NOT NULL default 'text';

ALTER TABLE `config_cfg`
ADD COLUMN
`cfg_data` text default NULL AFTER `cfg_order`;

Update `config_cfg` set
`cfg_data` = '{"Choices":["smtp","SendMail"]}',
`cfg_type` = 'choice'
where `cfg_id` = 25;

Update `config_cfg` set
`cfg_data` = '{"Choices":["miles","kilometers"]}',
`cfg_type` = 'choice'
where `cfg_id` = 64;

Update `config_cfg` set
`cfg_data` = '{"Choices":["Vanco","Authorize.NET"]}',
`cfg_type` = 'choice'
where `cfg_id` = 73;

Update `config_cfg` set
`cfg_data` = '{"Choices":["WebDAV","Local"]}',
`cfg_type` = 'choice'
where `cfg_id` = 1037;

Update `config_cfg` set
`cfg_data` = '{"Choices":["en_US","de_DE","en_AU","en_GB","es_ES","fr_FR","hu_HU","it_IT","nb_NO","nl_NL","pl_PL","pt_BR","ro_RO","ru_RU","se_SE","sq_AL","sv_SE","zh_CN","zh_TW"]}',
`cfg_type` = 'choice'
where `cfg_id` = 1037;

delete from config_cfg where cfg_id ='18';
delete from config_cfg where cfg_id ='2001';

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`, `cfg_data`) VALUES
(80, 'sEnableSelfRegistration', '1', 'boolean', '1', 'Set false to disable family self registration.', 'General', NULL, NULL),
(1044, 'sEnableIntegrityCheck', '1', 'boolean', '1', 'Enable Integrity Check', 'General', "Step5", NULL),
(1045, 'sIntegrityCheckInterval', '168', 'Text', '168', 'Interval in Hours for Integrity Check', 'General', "Step5", NULL),
(1046, 'sLastIntegrityCheckTimeStamp', '', 'Text', '', 'Last Integrity Check Timestamp', 'General', "Step5", NULL);
