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
