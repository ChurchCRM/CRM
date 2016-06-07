<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

DROP PROCEDURE IF EXISTS `migrateGroupKey`;
CREATE PROCEDURE `migrateGroupKey` (IN iGroupKey VARCHAR(64)) BEGIN
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


DROP PROCEDURE IF EXISTS `migratepledge_PLG`;
CREATE PROCEDURE `migratepledge_PLG` () BEGIN
  DECLARE count INT default 0;
  DECLARE done INT DEFAULT FALSE;
  DECLARE groupKey VARCHAR(64);
  DECLARE cur1 CURSOR FOR SELECT DISTINCT plg_GroupKey FROM pledge_plg;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  OPEN cur1;

  read_loop: LOOP
    FETCH cur1 INTO groupKey;
    IF done THEN
      LEAVE read_loop;
    END IF;
      CALL migrateGroupKey(@groupKey);
    SET count = count + 1;
 
  END LOOP;

  CLOSE cur1;
END;

