INSERT INTO `menuconfig_mcf` (`mid`, `name`, `parent`, `ismenu`, `content_english`, `content`, `uri`, `statustext`, `security_grp`, `session_var`, `session_var_in_text`, `session_var_in_uri`, `url_parm_name`, `active`, `sortorder`) VALUES
  (101, 'webcalendar', 'main', 0, '', 'WebCalendar', 'webcalendar.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4);

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
  (70, 'bEnableWebCalendar', 'true', 'boolean', 'true', '', 'General', NULL);

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
  (71, 'sWebCalendarPath', '/WebCalendar-1.2.7', 'text', '/WebCalendar-1.2.7', '', 'General', NULL);
