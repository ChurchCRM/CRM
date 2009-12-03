--        It is highly recommended you backup your MySQL database before executing this
--        script. To backup from the command prompt use the following.
--
-- system> mysqldump -u root -p db_name > filename.sql
--
-- Upon success filename.sql contains all the SQL to rebuild the database db_name.
-- In case you need to restore your backup use the following command.
--
-- system> mysql -u root -p db_name < filename.sql
--
--      The SQL script below will migrate your database from version 1.2.6 to 1.2.7.
--      There is no script to go back to 1.2.6.  If you need to roll back to 1.2.6 your
--      best bet is to restore your MySQL backup and install 1.2.6 PHP code.
--
--

-- Change int type to avoid wrap of values
ALTER TABLE `volunteeropportunity_vol` CHANGE `vol_ID` `vol_ID` INT( 3 ) NOT NULL AUTO_INCREMENT; 

-- Add vol_Order field to table so that we can alter display order of volunteer opps
ALTER TABLE `volunteeropportunity_vol` ADD COLUMN `vol_Order` int(3) NOT NULL default '0' AFTER `vol_ID`;

-- New config values to enable multiple fund input
INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES 
(57, 'bUseScannedChecks', '0', 'boolean', '0', 'Switch to enable use of checks scanned by a character scanner', 'General', NULL),
(58, 'bChecksPerDepositForm', '14', 'number', '14', 'Number of checks on the deposit form, typically 14', 'General', NULL);

-- New query
INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES (32, 'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID', 'Family Pledge by Fiscal Year', 'Pledge summary by family name for each fund for the selected fiscal year', 1);

INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES (32, 32, 1, '', 'Fiscal Year', 'Fiscal Year.', 'fyid', '9', 1, 0, '', 12, 9, 0, 0);

INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_ID`, `qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES 
(28, 32, '2007/2008', '12'),
(29, 32, '2008/2009', '13'),
(30, 32, '2009/2010', '14'),
(31, 32, '2010/2011', '15');

-- Change Birthday query to select Type (Member, Regular attender, etc) instead of hard-coding to Member.

UPDATE `query_qry` SET `qry_SQL`='SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_cls_ID=~percls~ AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay', `qry_Description`='People with birthdays in a particular month' WHERE `qry_ID`='18';

INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES (33, 18, 1, '', 'Classification', 'Member, Regular Attender, etc.', 'percls', '1', 1, 0, '', 12, 1, 1, 2);

INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_ID`, `qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES 
(32, 33, 'Member', '1'),
(33, 33, 'Regular Attender', '2'),
(34, 33, 'Guest', '3'),
(35, 33, 'Non-Attender', '4'),
(36, 33, 'Non-Attender (staff)', '5');

-- add egive to enums
UPDATE `deposit_dep` SET `dep_Type` enum('Bank','CreditCard','BankDraft','eGive') NOT NULL default 'Bank';

ALTER TABLE `deposit_dep` CHANGE `dep_Type` `dep_Type` ENUM( 'Bank', 'CreditCard', 'BankDraft', 'eGive') NOT NULL DEFAULT 'Bank';

ALTER TABLE `pledge_plg` CHANGE `plg_method` `plg_method` ENUM('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE') default NULL;

CREATE TABLE IF NOT EXISTS `egive_egv` (
  `egv_egiveID` varchar(16) character set utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL default '0',
  `egv_EditedBy` smallint(6) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
