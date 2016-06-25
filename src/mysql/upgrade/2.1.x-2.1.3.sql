SET @upgradeStartTime = NOW();

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1035, 'sEnableGravatarPhotos', '1', 'boolean', '1', 'lookup user images on Gravatar when no local image is present', 'General', NULL);

INSERT IGNORE INTO version_ver (ver_version, ver_update_start, ver_update_end) VALUES ('2.1.3',@upgradeStartTime,NOW());
