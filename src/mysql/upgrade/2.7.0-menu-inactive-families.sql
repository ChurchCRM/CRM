UPDATE `menuconfig_mcf` SET content_english = 'View Active Families', `content` = 'View Active Families' WHERE `mid` = 15;

DELETE FROM `menuconfig_mcf` where `mid` = 16 ;

INSERT INTO `menuconfig_mcf` (`mid`, `name`, `parent`, `ismenu`, `content_english`, `content`, `uri`, `statustext`, `security_grp`, `session_var`, `session_var_in_text`, `session_var_in_uri`, `url_parm_name`, `active`, `sortorder`, `icon`) VALUES
  (16, 'viewfamilyinactive', 'people', 0, 'View Inactive Families', 'View Inactive Families', 'FamilyList.php?mode=inactive', '', 'bAll', NULL, 0, 0, NULL, 1, 6, NULL);
