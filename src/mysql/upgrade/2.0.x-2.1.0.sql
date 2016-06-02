SET @upgradeStartTime = NOW();

ALTER TABLE `version_ver`
CHANGE COLUMN `ver_date` `ver_update_start` datetime default NULL;

ALTER TABLE `version_ver`
ADD COLUMN `ver_update_end` datetime default NULL AFTER `ver_update_start`;

INSERT IGNORE INTO version_ver (ver_version, ver_update_start, ver_update_end) VALUES ('2.1.0',@upgradeStartTime,NOW());