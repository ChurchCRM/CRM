SET @upgradeStartTime = NOW();

INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1036, 'sEnableRemoteBackups', '1', 'boolean', '1', 'Enable Remote Backups to Cloud Services', 'General', "Step5"),
(1037, 'sRemoteBackupType', 'WebDAV', 'Text', '', 'Cloud Service Type (Supported values: WebDAV)', 'General', "Step5"),
(1038, 'sRemoteBackupEndpoint', '', 'Text', '', 'Remote Backup Endpoint', 'General', "Step5"),
(1039, 'sRemoteBackupUsername', '', 'Text', '', 'Remote Backup Username', 'General', "Step5"),
(1040, 'sRemoteBackupPassword', '', 'Text', '', 'Remote Backup Password', 'General', "Step5"),
(1041, 'sRemoteBackupAutoInterval', '', 'Text', '', 'Interval in Hours for Automatic Remote Backups', 'General', "Step5"),
(1042, 'sLastBackupTimeStamp', '', 'Text', '', 'Last Backup Timestamp', 'General', "Step5");

INSERT IGNORE INTO version_ver (ver_version, ver_update_start, ver_update_end) VALUES ('2.2.0',@upgradeStartTime,NOW());
