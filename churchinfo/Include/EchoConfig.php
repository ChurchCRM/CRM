<?php
/*******************************************************************************
 *
 *  filename    : /Include/CanvassUtilities.php
 *  last change : 2005-02-21
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2005 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

function CanvassSetDefaultFY ($iFYID)
{
	$sSQL = "UPDATE user_usr SET usr_defaultFY='" . $iFYID . "';";
	RunQuery ($sSQL);
}

function CanvassSetAllOkToCanvass ()
{
	$sSQL = "UPDATE family_fam SET fam_OkToCanvass='TRUE' WHERE 1;";
	RunQuery ($sSQL);
}

function CanvassClearAllOkToCanvass ()
{
	$sSQL = "UPDATE family_fam SET fam_OkToCanvass='FALSE' WHERE 1;";
	RunQuery ($sSQL);
}

function CanvassClearCanvasserAssignments ()
{
	$sSQL = "UPDATE family_fam SET fam_Canvasser=0 WHERE 1;";
	RunQuery ($sSQL);
}

function CanvassGetCanvassers ($groupName)
{
	// Find the canvassers group
	$sSQL = "SELECT grp_ID AS iCanvassGroup FROM group_grp WHERE grp_Name=\"" . $groupName . "\";";
	$rsGroupData = RunQuery($sSQL);
	$aGroupData = mysql_fetch_array($rsGroupData);
	if (mysql_num_rows ($rsGroupData) == 0) {
		return (0);
	}
	extract($aGroupData);

	// Get the canvassers from the Canvassers group
	$sSQL = "SELECT per_ID, per_FirstName, per_LastName FROM person_per, person2group2role_p2g2r WHERE per_ID = p2g2r_per_ID AND p2g2r_grp_ID = " . $iCanvassGroup . " ORDER BY per_LastName,per_FirstName;";
	$rsCanvassers = RunQuery($sSQL);
	$numCanvassers = mysql_num_rows ($rsCanvassers);
	if ($numCanvassers == 0) {
		return (0);
	}
	return ($rsCanvassers);
}

function CanvassAssignCanvassers ($groupName)
{
	$rsCanvassers = CanvassGetCanvassers ($groupName);

	// Get all the families that need canvassers
	$sSQL = "SELECT fam_ID FROM family_fam WHERE fam_OkToCanvass='TRUE' AND fam_Canvasser=0 ORDER BY RAND();";
	$rsFamilies = RunQuery($sSQL);
	$numFamilies = mysql_num_rows ($rsFamilies);
	if ($numFamilies == 0) {
		return (gettext ("No families need canvassers assigned"));
	}

	while ($aFamily = mysql_fetch_array ($rsFamilies)) {
		if (! ($aCanvasser = mysql_fetch_array ($rsCanvassers))) {
			mysql_data_seek ($rsCanvassers, 0);
			$aCanvasser = mysql_fetch_array ($rsCanvassers);
		}
		$sSQL = "UPDATE family_fam SET fam_Canvasser=" . $aCanvasser["per_ID"] . " WHERE fam_ID= ". $aFamily["fam_ID"];
		RunQuery($sSQL);
	}

	$ret = sprintf (gettext ("Canvassers assigned at random to %d families."), $numFamilies);
	return ($ret);
}

function CanvassAssignNonPledging ($groupName, $iFYID)
{
	$rsCanvassers = CanvassGetCanvassers ($groupName);

	// Get all the families which need canvassing
	$sSQL = "SELECT *, a.per_FirstName AS CanvasserFirstName, a.per_LastName AS CanvasserLastName FROM family_fam 
	         LEFT JOIN person_per a ON fam_Canvasser = a.per_ID
			 WHERE fam_OkToCanvass=\"TRUE\" ORDER BY RAND()";
	$rsFamilies = RunQuery($sSQL);

	$numFamilies = 0;

	while ($aFamily = mysql_fetch_array($rsFamilies)) {
		// Get pledges for this fiscal year, this family
		$sSQL = "SELECT plg_Amount FROM pledge_plg 
				 WHERE plg_FYID = " . $iFYID . " AND plg_PledgeOrPayment=\"Pledge\" AND plg_FamID = " . $aFamily["fam_ID"] . " ORDER BY plg_Amount DESC";
		$rsPledges = RunQuery($sSQL);

		$pledgeCount = mysql_num_rows ($rsPledges);
		if ($pledgeCount == 0) {
			++$numFamilies;
			if (! ($aCanvasser = mysql_fetch_array ($rsCanvassers))) {
				mysql_data_seek ($rsCanvassers, 0);
				$aCanvasser = mysql_fetch_array ($rsCanvassers);
			}
			$sSQL = "UPDATE family_fam SET fam_Canvasser=" . $aCanvasser["per_ID"] . " WHERE fam_ID= ". $aFamily["fam_ID"];
			RunQuery($sSQL);
		}
	}
	$ret = sprintf (gettext ("Canvassers assigned at random to %d non-pledging families."), $numFamilies);
	return ($ret);
}

?>
