ALTER TABLE `config_cfg`
MODIFY `cfg_type` ENUM('text','number','date','boolean','textarea','json','choice', 'country') NOT NULL default 'text';

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(1047, 'sChurchCountry', 'United States', 'country', '', 'Church Country', 'ChurchInfoReport';

