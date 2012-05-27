<?php
/*******************************************************************************
 *
 *  filename    : presentdemo.php
 *  description : 
 *
 *  http://www.churchdb.org/
 *  Copyright 2011-2012 Michael Wilt
 *  
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/
session_name ("privatedemo");
session_start ();
?>

<html>
<head>
<meta http-equiv="Content-Language" content="en-us">
<title>Personal demo created</title>
</head>
<body>

<h1>Personal demo creation</h1>
Please wait for this page to finish loading and read the instructions carefully.
<p>

<?php
require_once ('Config.php');
require_once ('Functions.php');

$include_path = dirname(__FILE__);

$cnTempDB = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD)
        or die ('Cannot connect to the MySQL server because: ' . mysql_error());
mysql_select_db($sDATABASE)
        or die ('Cannot select the MySQL database because: ' . mysql_error());

$idIn = $_GET['ContactID'];
        
$sSQL = "SELECT * FROM AdminContact WHERE ac_id = $idIn";
$rsAC = mysql_query ($sSQL);
$rsACArr = mysql_fetch_array ($rsAC);
extract ($rsACArr);

if ($ac_dir == "") {
	// Directory has not been created yet
	
	// find a free database
	$sSQL = "SELECT * from DBPool WHERE ISNULL(dbp_assignedto) LIMIT 1";
	$rsDBP = mysql_query ($sSQL);
	
	if (mysql_num_rows ($rsDBP) == 0) {
		print "Sorry, all demos are currently taken.  Please try again tomorrow";
		exit;
	}

	$rsDBPArr = mysql_fetch_array ($rsDBP);
	extract ($rsDBPArr);

	// assign this database to this admin contact
	$sSQL = "UPDATE DBPool SET dbp_assignedto='$ac_id', dbp_assigneddate=NOW() WHERE dbp_id=$dbp_id";
	$rsUpdateAssignment = mysql_query ($sSQL);
	
	$randNo = rand();
	$ac_dir = "demo" . $randNo;
	$sSQL = "UPDATE AdminContact SET ac_dir='$ac_dir' WHERE ac_id=$idIn";
	mysql_query ($sSQL);	

	// make the new directory, put a vanilla copy of ChurchInfo inside
	$sCmd = "mkdir " . $ac_dir;
	system ($sCmd);
	
	$sCmd = "tar xf $useTarFile --directory=$ac_dir";
	system ($sCmd);

	$sCmd = "mv $ac_dir/churchinfo/php.ini_REGISTER_GLOBALS_OFF $ac_dir/churchinfo/php.ini";
	system ($sCmd);

	// Initialize the database
	$sCmd = "mysqldump --host=$dbp_hostname -u$dbp_username -p$dbp_pw --add-drop-table --no-data $dbp_dbname | grep ^DROP | mysql --host=$dbp_hostname -u$dbp_username -p$dbp_pw $dbp_dbname";

	system ($sCmd);
	$sCmd = "mysql --host=$dbp_hostname --user=$dbp_username --password=$dbp_pw --database=$dbp_dbname --batch --execute=\"source $ac_dir/churchinfo/SQL/Install.sql\"";
	system ($sCmd);
	
	// Update Include/Config.php
	$sedCmd = array();
	$sedCmd[0] = "\"s/sSERVERNAME = 'localhost'/sSERVERNAME = '$dbp_hostname'/g\"";
	$sedCmd[1] = "\"s/sUSER = 'churchinfo'/sUSER = '$dbp_username'/g\"";
	$sedCmd[2] = "\"s/sPASSWORD = 'churchinfo'/sPASSWORD = '$dbp_pw'/g\"";
	$sedCmd[3] = "\"s/sDATABASE = 'churchinfo'/sDATABASE = '$dbp_dbname'/g\"";
	$sedCmd[4] = "\"s.sRootPath='/churchinfo'.sRootPath = '/privatedemo/$ac_dir/churchinfo'.g\"";
	foreach ($sedCmd as $sedStr) {
		$cmdStr = "sed -i $sedStr $ac_dir/churchinfo/Include/Config.php";
		system ($cmdStr);
	}
}

print "Your private demo URL is: <a href='http://www.churchdb.org/privatedemo/$ac_dir/churchinfo'>http://www.churchdb.org/privatedemo/$ac_dir/churchinfo</a>";
print "<p>Please bookmark this URL so you can remember where your demo is located during your experiments.  Your initial login will require user name \"admin\" and password \"churchinfoadmin\".  At the first login it will ask you to change it.";

?>
