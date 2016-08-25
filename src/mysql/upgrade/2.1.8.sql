INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1036, 'sEnableExternalBackupTarget', '0', 'boolean', '0', 'Enable Remote Backups to Cloud Services', 'General', "Step5"),
(1037, 'sExternalBackupType', 'WebDAV', 'Text', '', 'Cloud Service Type (Supported values: WebDAV, Local)', 'General', "Step5"),
(1038, 'sExternalBackupEndpoint', '', 'Text', '', 'Remote Backup Endpoint', 'General', "Step5"),
(1039, 'sExternalBackupUsername', '', 'Text', '', 'Remote Backup Username', 'General', "Step5"),
(1040, 'sExternalBackupPassword', '', 'Text', '', 'Remote Backup Password', 'General', "Step5"),
(1041, 'sExternalBackupAutoInterval', '', 'Text', '', 'Interval in Hours for Automatic Remote Backups', 'General', "Step5"),
(1042, 'sLastBackupTimeStamp', '', 'Text', '', 'Last Backup Timestamp', 'General', "Step5");
