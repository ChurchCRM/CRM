CREATE TABLE IF NOT EXISTS `typeofmbr` (
    `typeid` tinyint(3) NOT NULL,
    `Name` tinytext NOT NULL,
    PRIMARY KEY (`typeid`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `typeofmbr` (`typeid`, `Name`) VALUES (1, 'Business'), (2, 'Family'), (3, 'Person');

CREATE TABLE IF NOT EXISTS `contrib_con` (
  `con_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `con_ContribID` mediumint(9) unsigned NOT NULL,
  `con_TypeOfMbr` enum('0','1','2','3') COLLATE utf8_unicode_ci DEFAULT NULL,
  `con_DepID` mediumint(9) unsigned DEFAULT NULL,
  `con_Date` date DEFAULT NULL,
  `con_Method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE'),
  `con_CheckNo` bigint(16) unsigned,
  `con_Comment` text COLLATE utf8_unicode_ci,
  `con_DateEntered` datetime DEFAULT NULL,
  `con_EnteredBy` mediumint(9) unsigned DEFAULT NULL,
  `con_DateLastEdited` datetime DEFAULT NULL,
  `con_EditedBy` mediumint(9) unsigned DEFAULT NULL,
  PRIMARY KEY (`con_ID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

  CREATE TABLE IF NOT EXISTS `contrib_split` (
  `spl_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `spl_ConID` mediumint(9) unsigned NOT NULL,
  `spl_FundID` tinyint(3) unsigned NOT NULL,
  `spl_Amount` decimal(8,2) unsigned NOT NULL,
  `spl_Comment` text COLLATE utf8_unicode_ci,
  `spl_NonDeductible` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`spl_ID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

  ALTER TABLE person_per
  ADD per_inactive tinyint(1) NOT NULL DEFAULT 0;
  
delete from list_lst WHERE  lst_OptionName = 'bCommunication';
delete from list_lst WHERE  lst_OptionName = 'bMenuOptions';
