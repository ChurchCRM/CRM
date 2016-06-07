SET @upgradeStartTime = NOW();

ALTER TABLE `version_ver`
CHANGE COLUMN `ver_date` `ver_update_start` datetime default NULL;

ALTER TABLE `version_ver`
ADD COLUMN `ver_update_end` datetime default NULL AFTER `ver_update_start`;

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`, `cfg_order`) 
VALUES
  (1034, 'useCurrencyDenominations', '1', 'boolean', '1',
   'Display currency denominations during pledge entry.  If true, payment totals are calculated based on the sum of entered currencies.  If false, the payment total may be entered directly',
   'General', "Step8",25);

DROP TABLE IF EXISTS `currency_denominations_cdem`;
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

-- ------ Notes #608 - start

ALTER TABLE note_nte
  ADD COLUMN nte_Type VARCHAR(45) NOT NULL DEFAULT 'note' AFTER nte_EditedBy;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "Original Entry", per_EnteredBy, per_DateEntered, "create"
from person_per;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "Last Edit", per_EditedBy, per_DateLastEdited, "edit"
from person_per
where per_DateLastEdited is not null;

-- ------ Notes #608 - end

DROP PROCEDURE IF EXISTS migrateGroupKey;
CREATE PROCEDURE migrateGroupKey (IN iGroupKey VARCHAR(64))
BEGIN
  DECLARE count INT default 0;
  DECLARE done INT DEFAULT FALSE;
  DECLARE groupKey VARCHAR(64);
  DECLARE tmpPlgID INT(7);
  DECLARE plgID INT(7);
  DECLARE fundID TINYINT(3);
  DECLARE plgAmount DECIMAL(8);
  DECLARE cur1 CURSOR FOR SELECT plg_plgID, plg_fundID,plg_amount FROM pledge_plg where plg_GroupKey = iGroupKey COLLATE utf8_unicode_ci;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  OPEN cur1;

  read_loop: LOOP
    FETCH cur1 INTO plgID,fundID,plgAmount;
    IF count > 0 THEN 
      SET plgID = tmpPlgID; 
    ELSE
       SET tmpPlgID = plgID;
    END IF;

    IF done THEN
      LEAVE read_loop;
    END IF;
    INSERT INTO pledgesplit_pls (pls_plgID, pls_fundID,pls_amount) VALUES (plgID, fundID, plgAmount );
    IF count >0 THEN
     
      SET count = 0;
      DELETE FROM pledge_plg WHERE plg_plgID = plgID;
    END IF;
    SET count = count + 1;
 
  END LOOP;

  CLOSE cur1;
END;


/*CALL foreach('SELECT DISTINCT plg_GroupKey in pledge_plg', 'migrateGroupKey(${1})');*/

INSERT IGNORE INTO version_ver (ver_version, ver_update_start, ver_update_end) VALUES ('2.1.0',@upgradeStartTime,NOW());
