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

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`, `cfg_order`) 
VALUES
  (1034, 'useCurrencyDenominations', '1', 'boolean', '1',
   'Display currency denominations during pledge entry.  If true, payment totals are calculated based on the sum of entered currencies.  If false, the payment total may be entered directly',
   'General', "Step8",25);

CREATE TABLE `pledgesplit_pls` (
  `pls_pledgesplitID` mediumint(9) NOT NULL auto_increment,
  `pls_plg_ID` mediumint(9) NOT NULL, 
  `pls_amount` decimal(8,2) default NULL,
  `pls_comment` text,
  `pls_DateLastEdited` date NOT NULL default '0000-00-00',
  `pls_EditedBy` mediumint(9) NOT NULL default '0',
  `pls_fundID` tinyint(3) unsigned default NULL,
  `pls_NonDeductible` decimal(8,2) NOT NULL default '0',
  PRIMARY KEY  (`pls_pledgesplitID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;