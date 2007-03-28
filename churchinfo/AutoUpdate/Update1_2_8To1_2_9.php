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

$sError = mysql_error();

?>
