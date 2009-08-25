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
(58, 'bChecksPerDepositForm', '14', 'boolean', '14', 'Number of checks on the deposit form, typically 14', 'General', NULL);
