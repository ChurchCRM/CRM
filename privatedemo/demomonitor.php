<?php
/*******************************************************************************
 *
 *  filename    : demomonitor.php
 *  description : displays the private demos and allows them to be recycled
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

require_once ('Config.php');
require_once ('Functions.php');

$include_path = dirname(__FILE__);

$cnTempDB = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD)
        or die ('Cannot connect to the MySQL server because: ' . mysql_error());
mysql_select_db($sDATABASE)
        or die ('Cannot select the MySQL database because: ' . mysql_error());

// see if an expire button was pressed
$sSQL = "SELECT a.ac_dir, dbp_id, a.ac_id FROM DBPool LEFT JOIN AdminContact a ON dbp_assignedto = a.ac_id WHERE dbp_assignedto IS NOT NULL";
$rsDBP = mysql_query ($sSQL);
while (list ($dbdir, $dbid, $acid) = mysql_fetch_row($rsDBP)) {
	if (isset ($_POST["Expire$dbid"])) {
		print "Expired demo # $dbid from directory $dbdir";
		$cmdStr = "rm -rf $dbdir";
		system ($cmdStr);
		$sSQL = "UPDATE DBPool SET dbp_assignedto=NULL WHERE dbp_id=$dbid";
		mysql_query ($sSQL);
	}
	if (isset ($_POST["ReceivedEmail$dbid"])) {
		$sSQL = "UPDATE AdminContact SET ac_received_email=1 WHERE ac_id=$acid";
		mysql_query ($sSQL);
	}
}

// see if the update mailing list button was pressed
if (isset ($_POST["UpdateMailingList"])) {
	// gather up all the email addresses that have been entered so far
	$emailArr = array();
	
	$sSQL = "SELECT ac_id,ac_email FROM AdminContact";
	$rsDBP = mysql_query ($sSQL);
	
	while ($emailArr[] = mysql_fetch_row($rsDBP))
	;

	// switch databases to the mailing list one to poke in all these email addresses
	$cnTempDB = mysql_connect($sPhpMailSERVERNAME,$sPhpMailUSER,$sPhpMailPASSWORD)
	        or die ('Cannot connect to the MySQL server because: ' . mysql_error());
	mysql_select_db($sPhpMailDATABASE)
	        or die ('Cannot select the MySQL database because: ' . mysql_error());
	        
	// always rebuild list 1 to match all the people who have registered for private demos.
	$sSQL = "DELETE FROM phplist_listuser WHERE listid=1";
	$rsDBP = mysql_query ($sSQL);
	
	foreach ($emailArr as $oneRow ) {
		$id = $oneRow[0];
		$email = $oneRow[1];

		// this INSERT IGNORE will quietly do nothing for existing users
		$sSQL = "INSERT IGNORE INTO phplist_user_user (id, email, confirmed, uniqid, htmlemail) VALUES ($id, \"$email\", 1, $id, 1)";
		$rsDBP = mysql_query ($sSQL);
		
		$sSQL = "INSERT IGNORE INTO phplist_listuser (userid, listid) VALUES ($id, 1)";
		$rsDBP = mysql_query ($sSQL);
	}

	// switch databases back to the default for personal demo monitor
	$cnTempDB = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD)
	        or die ('Cannot connect to the MySQL server because: ' . mysql_error());
	mysql_select_db($sDATABASE)
	        or die ('Cannot select the MySQL database because: ' . mysql_error());		
}
?>

<html>
<head>
<meta http-equiv="Content-Language" content="en-us">
<title>Personal demo monitor</title>
</head>
<body>

<h1>Personal demo monitor</h1>

<?php
$sSQL = "SELECT a.ac_firstname, a.ac_lastname, a.ac_received_email, a.ac_organization, a.ac_city, a.ac_state, dbp_id, dbp_assigneddate, a.ac_dir, a.ac_email FROM DBPool LEFT JOIN AdminContact a ON dbp_assignedto = a.ac_id WHERE dbp_assignedto IS NOT NULL ORDER BY dbp_assigneddate";
$rsDBP = mysql_query ($sSQL);
?>

<form id="form_demomonitor" method="POST" action="<?php echo $_SERVER['PHP_SELF'] ?>">

<table cellpadding="3">

<?php

$bccEmail = "";
$activeDemoCnt = 0;

echo "<tr><td>Expire</td><td>First</td><td>Last</td><td>Received Email</td><td>Organization</td><td>City</td><td>State</td><td>Directory</td><td>Mail</td><td>Date</td></tr>";

while (list ($firstname, $lastname, $receivedEmail, $organization, $city, $state, $dbid, $dbdate, $demodir, $email) = mysql_fetch_row($rsDBP))
{
	echo "<tr><td><input type=\"submit\" class=\"button\" value=\"Expire\" name=\"Expire$dbid\"></td>";
	echo "\t<td>$firstname</td>\n";
	echo "\t<td>$lastname</td>\n";
	echo "\t<td><input type=\"checkbox\" onchange=\"javascript:this.form.submit()\" name=\"ReceivedEmail$dbid\" ";
	if ($receivedEmail)
		echo "checked";
	echo "></td>";
	echo "\t<td>$organization</td>\n";
	echo "\t<td>$city</td>\n";
	echo "\t<td>$state</td>\n";
	echo "\t<td>$demodir</td>\n";
	echo "\t<td><a href=\"mailto:$email\">$email</a></td>\n";
	if ($email != "") {
		if ($bccEmail == "") {
			$bccEmail = "$email";
		} else {
			$bccEmail .= ",$email";
		}
	}
	echo "\t<td>$dbdate</td>\n";
	echo "</tr>\n";
	$activeDemoCnt += 1;
}
echo "<tr><td><input type=\"submit\" class=\"button\" value=\"Update Mailing List\" name=\"UpdateMailingList\"></td>";

print "<tr><h2>Total active demos $activeDemoCnt</h2></tr>\n";
print "<tr><a href=\"mailto:?bcc=$bccEmail\">Email all (BCC)</a></tr>\n";
?>

</table>
</form>

