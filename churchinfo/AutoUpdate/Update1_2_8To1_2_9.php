<?php
/*******************************************************************************
*
*  filename    : Update1_2_8To1_2_9.php
*  description : Update MySQL database from 1.2.8 To 1.2.9
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2007 Ed Davis
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('1.2.9',NOW())";
RunQuery($sSQL, FALSE); // False means do not stop on error

$sSQL = "INSERT INTO `config_cfg` VALUES (56, 'bUseGoogleGeocode', '1', 'boolean', '1', 'Set true to use the Google geocoder.  Set false to use rpc.geocoder.us.', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_recipient_pending_erp` ADD COLUMN `erp_failed_time` datetime";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_num_sent`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_num_left`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_last_sent_addr`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_last_sent_time`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_last_attempt_addr`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` DROP COLUMN `emp_last_attempt_time`";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `email_message_pending_emp` ADD COLUMN `emp_to_send` smallint(5) unsigned NOT NULL DEFAULT '0'";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `query_qry` VALUES (30,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name, fam_address1, fam_city, fam_state, fam_zip FROM person_per join family_fam on per_fam_id=fam_id where per_fmr_id<>3 and per_fam_id in (select fam_id from family_fam inner join pledge_plg a on a.plg_famID=fam_ID and a.plg_FYID=~fyid1~ and a.plg_amount>0) and per_fam_id not in (select fam_id from family_fam inner join pledge_plg b on b.plg_famID=fam_ID and b.plg_FYID=~fyid2~ and b.plg_amount>0)','Missing pledges','Find people who pledged one year but not another',1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `query_qry` VALUES (31, 'select per_ID as AddToCart, per_FirstName, per_LastName, per_email from person_per, autopayment_aut where aut_famID=per_fam_ID and aut_CreditCard!=\"\" and per_email!=\"\" and (per_fmr_ID=1 or per_fmr_ID=2 or per_cls_ID=1)', 'Credit Cart People', 'People who are configured to pay by credit card.', 0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (18, 30, '2005/2006', '10')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (19, 30, '2006/2007', '11')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (20, 30, '2007/2008', '12')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (21, 30, '2008/2009', '13')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (22, 31, '2005/2006', '10')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (23, 31, '2006/2007', '11')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (24, 31, '2007/2008', '12')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (25, 31, '2008/2009', '13')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameters_qrp` VALUES (30,30,1,'','First Fiscal Year','Pledged this year','fyid1','9',1,0,'',12,9,0,0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT IGNORE INTO `queryparameters_qrp` VALUES (31,30,1,'','Second Fiscal Year','but not this year','fyid2','9',1,0,'',12,9,0,0)";
RunQuery($sSQL, FALSE);

$sSQL = "UPDATE config_cfg SET cfg_value=concat(cfg_value,',30,31') WHERE cfg_name='aFinanceQueries'";
RunQuery($sSQL, FALSE);

$sError = mysql_error();

?>
