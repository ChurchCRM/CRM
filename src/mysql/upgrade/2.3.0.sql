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
`cfg_data` = '{"Choices":["en_US","de_DE","en_AU","en_GB","es_ES","fr_FR","hu_HU","it_IT","nb_NO","nl_NL","pl_PL","pt_BR","ro_RO","ru_RU","se_SE","sq_AL","sv_SE","vi_VN","zh_CN","zh_TW"]}',
`cfg_type` = 'choice'
where `cfg_id` = 39;

Update `config_cfg` set
`cfg_value` = 0
where `cfg_name` = "bRegistered";

delete from config_cfg where cfg_id ='1';
delete from config_cfg where cfg_id ='18';
delete from config_cfg where cfg_id ='2001';

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(80, 'sEnableSelfRegistration', '0', 'boolean', '0', 'Set true to enable family self registration.', 'General'),
(100, 'sPhoneFormat', '(999) 999-9999', 'text', '(999) 999-9999', '', 'General'),
(101, 'sPhoneFormatWithExt', '(999) 999-9999 x99999', 'text', '(999) 999-9999 x99999', '', 'General'),
(102, 'sDateFormatLong', 'yyyy-mm-dd', 'text', 'yyyy-mm-dd', '', 'General'),
(103, 'sDateFormatNoYear', 'DD/MM', 'text', 'DD/MM', '', 'General'),
(104, 'sDateFormatShort', 'yy-mm-dd', 'text', 'yy-mm-dd', '', 'General'),
(1044, 'sEnableIntegrityCheck', '1', 'boolean', '1', 'Enable Integrity Check', 'General'),
(1045, 'sIntegrityCheckInterval', '168', 'Text', '168', 'Interval in Hours for Integrity Check', 'General'),
(1046, 'sLastIntegrityCheckTimeStamp', '', 'Text', '', 'Last Integrity Check Timestamp', 'General');

delete from config_cfg where cfg_id ='61';
delete from config_cfg where cfg_id ='62';
delete from config_cfg where cfg_id ='63';

-- remove unsigned
ALTER TABLE `person_per`
  CHANGE COLUMN `per_EnteredBy` `per_EnteredBy` SMALLINT(5) NOT NULL DEFAULT '0' COMMENT '' ;

ALTER TABLE `family_fam`
  CHANGE COLUMN `fam_EnteredBy` `fam_EnteredBy` SMALLINT(5) NOT NULL DEFAULT '0' COMMENT '' ;

ALTER TABLE `note_nte`
  CHANGE COLUMN `nte_EnteredBy` `nte_EnteredBy` MEDIUMINT(8) NOT NULL DEFAULT '0' COMMENT '' ;
