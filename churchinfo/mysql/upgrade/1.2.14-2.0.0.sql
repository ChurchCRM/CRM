DELETE FROM config_cfg
WHERE cfg_id IN (2, 4, 15, 17, 24, 32, 35, 999);

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
  (2, 'debug', '1', 'boolean', '1',
   'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchCRM, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.',
   'General', NULL),
  (4, 'sFPDF_PATH', 'vendor/fpdf17', 'text', 'vendor/fpdf17', 'FPDF library', 'General', NULL),
  (15, 'sDisallowedPasswords', 'churchcrm,password,god,jesus,church,christian', 'text', 'churchcrm,password,god,jesus,church,christian', 'A comma-seperated list of disallowed (too obvious) passwords.', 'General', NULL),
  (24, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchCRM. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General', NULL),
  (999, 'bRegistered', '0', 'boolean', '0',
   'ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General', NULL),
  (2001, 'mailChimpApiKey', '', 'text', '', 'see http://kb.mailchimp.com/accounts/management/about-api-keys', 'General', NULL),
  (1034, 'sChurchChkAcctNum', '111111111', 'text', '', 'Church Checking Account Number', 'ChurchInfoReport', NULL);
UPDATE user_usr
SET usr_Style = "skin-blue";

ROP TABLE IF EXISTS `currency_denominations_cdem`;
CREATE TABLE `currency_denominations_cdem` (
 `cdem_denominationID` mediumint(9) NOT NULL auto_increment,
 `cdem_denominationName` text,
 `cdem_denominationValue` decimal(8,2) default NULL,
 `cdem_denominationClass` text,
 PRIMARY KEY  (`cdem_denominationID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

INSERT INTO `currency_denominations_cdem` (`cdem_denominationName`, `cdem_denominationValue`, `cdem_denominationClass`) VALUES 
("1¢", 0.01,'COIN'),
("5¢", .05,'COIN'),
("10¢", .10,'COIN'),
("25¢", .25,'COIN'),
("50¢", .5,'COIN'),
("$1 Coin", 1,'COIN'),
("$1", 1,'BILL'),
("$2", 2,'BILL'),
("$5", 5,'BILL'),
("$10", 10,'BILL'),
("$20", 20,'BILL'),
("$50", 50,'BILL'),
("$100", 100,'BILL');

DROP TABLE IF EXISTS `pledge_denominations_pdem`;
CREATE TABLE `pledge_denominations_pdem`(
 `pdem_pdemID` mediumint(9) NOT NULL auto_increment,
 `pdem_plg_GroupKey` text,
 `plg_depID` mediumint(9) unsigned default NULL,
 `pdem_denominationID` text,
 `pdem_denominationQuantity` mediumint(9) default NULL,
 PRIMARY KEY  (`pdem_pdemID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;
