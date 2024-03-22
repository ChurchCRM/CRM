alter table version_ver add `ver_update_start` datetime DEFAULT NULL;

alter table version_ver add `ver_update_end` datetime DEFAULT NULL;

alter table version_ver drop column `ver_date`;

DELETE FROM config_cfg WHERE cfg_id IN (2, 4, 15, 17, 24, 32, 35, 999);

INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section, cfg_category)
VALUES
  (2, 'debug', '1', 'boolean', '1',
   'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchCRM, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.',
   'General', NULL),
  (15, 'sDisallowedPasswords', 'churchcrm,password,god,jesus,church,christian', 'text', 'churchcrm,password,god,jesus,church,christian', 'A comma-separated list of disallowed (too obvious) passwords.', 'General', NULL),
  (24, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchCRM. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General', NULL),
  (999, 'bRegistered', '0', 'boolean', '0',
   'ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General', NULL),
  (2000, 'mailChimpApiKey', '', 'text', '', 'see http://kb.mailchimp.com/accounts/management/about-api-keys', 'General', NULL),
  (1034, 'sChurchChkAcctNum', '111111111', 'text', '', 'Church Checking Account Number', 'ChurchInfoReport', NULL);
UPDATE user_usr
SET usr_Style = "skin-blue";

-- --NOTE-- removed in 2.6.0 so commenting out
-- ALTER TABLE config_cfg
-- ADD COLUMN cfg_order INT NULL COMMENT '' AFTER cfg_category;
