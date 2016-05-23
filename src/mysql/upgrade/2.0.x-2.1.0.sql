ALTER TABLE `version_ver`
 CHANGE COLUMN `ver_date` `ver_update_start` datetime default NULL;

ALTER TABLE `version_ver`
 ADD COLUMN `ver_update_end` datetime default NULL AFTER `ver_update_start`;