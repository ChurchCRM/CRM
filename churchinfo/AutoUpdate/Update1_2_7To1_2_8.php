<?php
/*******************************************************************************
*
*  filename    : Update1.2.7To1.2.8.php
*  description : Update MySQL database from 1.2.7 To 1.2.8
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2006 Ed Davis
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

$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('1.2.8',NOW())";
RunQuery($sSQL, FALSE); // False means do not stop on error
$sError = mysql_error();

?>
