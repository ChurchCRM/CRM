<?php
/*******************************************************************************
 *
 *  filename    : SelectDelete
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Lewis Franklin
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must have Delete records permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bDeleteRecords'])
{
	Redirect("Menu.php");
	exit;
}

if (!empty($_GET["FamilyID"])) $iFamilyID = FilterInput($_GET["FamilyID"],'int');
if (!empty($_GET["PersonID"])) $iPersonID = FilterInput($_GET["PersonID"],'int');
if (!empty($_GET["DonationFamilyID"])) $iDonationFamilyID = FilterInput($_GET["DonationFamilyID"],'int');
if (!empty($_GET["mode"])) $sMode = $_GET["mode"];

if ($_GET["CancelFamily"]){
	Redirect("FamilyView.php?FamilyID=$iFamilyID");
	exit;
}

// Move Donations from 1 family to another
if ($_SESSION['bFinance'] && $_GET["MoveDonations"] && $iFamilyID && $iDonationFamilyID && $iFamilyID != $iDonationFamilyID) {
	$today = date("Y-m-d");
	$sSQL = "UPDATE pledge_plg SET plg_FamID='$iDonationFamilyID',
		plg_DateLastEdited ='$today', plg_EditedBy='".$_SESSION["iUserID"]
		."' WHERE plg_FamID='$iFamilyID'";
	RunQuery($sSQL);
	$DonationMessage = "<p><b><font color=red>".gettext("All donations from this family have been moved to another family.") . "</font></b></p>";
}

//Set the Page Title

if($sMode == 'person')
	$sPageTitle = gettext("Person Delete Confirmation");
else
	$sPageTitle = gettext("Family Delete Confirmation");

function DeletePerson($iPersonID)
{
	// Remove person from all groups they belonged to
	$sSQL = "SELECT p2g2r_grp_ID FROM person2group2role_p2g2r WHERE p2g2r_per_ID = " . $iPersonID;
	$rsAssignedGroups = RunQuery($sSQL);
	while ($aRow = mysql_fetch_array($rsAssignedGroups))
	{
		extract($aRow);
		RemoveFromGroup($iPersonID, $p2g2r_grp_ID);
	}

	// Remove custom field data
	$sSQL = "DELETE FROM person_custom WHERE per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Remove note data
	$sSQL = "DELETE FROM note_nte WHERE nte_per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Delete the Person record
	$sSQL = "DELETE FROM person_per WHERE per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Remove person property data
	$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='p'";
	$rsProps = RunQuery($sSQL);

	while($aRow = mysql_fetch_row($rsProps)) {
		$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iPersonID;
		RunQuery($sSQL);
	}

	// Delete any User record
	// $sSQL = "DELETE FROM user_usr WHERE usr_per_ID = " . $iPersonID;
	// RunQuery($sSQL);

	// Make sure person was not in the cart
	RemoveFromPeopleCart($iPersonID);

	// Delete the photo files, if they exist
	$photoThumbnail = "Images/Person/thumbnails/" . $iPersonID . ".jpg";
	if (file_exists($photoThumbnail))
		unlink ($photoThumbnail);
	$photoFile = "Images/Person/" . $iPersonID . ".jpg";
	if (file_exists($photoFile))
		unlink ($photoFile);
}

//Do we have deletion confirmation?
if (isset($_GET["Confirmed"]))
{
	if ($sMode == 'person')
	{
		// Delete Person
		// Make sure this person is not a user
		$sSQL = "SELECT '' FROM user_usr WHERE usr_per_ID = " . $iPersonID;
		$rsUser = RunQuery($sSQL);
		$bIsUser = (mysql_num_rows($rsUser) > 0);

		if (!$bIsUser)
		{
			DeletePerson($iPersonID);

			// Redirect back to the list
			Redirect("SelectList.php?mode=person");
		}
	}
	else
	{
		// Delete Family
		// Delete all associated Notes associated with this Family record
		$sSQL = "DELETE FROM note_nte WHERE nte_fam_ID = " . $iFamilyID;
		RunQuery($sSQL);
		
		// Delete Family pledges
		$sSQL = "DELETE FROM pledge_plg WHERE plg_PledgeOrPayment = 'Pledge' AND plg_FamID = " . $iFamilyID;
		RunQuery($sSQL);

		// Remove family property data
		$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='f'";
		$rsProps = RunQuery($sSQL);

		while($aRow = mysql_fetch_row($rsProps)) {
			$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iFamilyID;
			RunQuery($sSQL);
		}

		if (isset($_GET["Members"]))
		{
			// Delete all persons that were in this family
			$sSQL = "SELECT per_ID FROM person_per WHERE per_fam_ID = " . $iFamilyID;
			$rsPersons = RunQuery($sSQL);
			while($aRow = mysql_fetch_row($rsPersons))
			{
				DeletePerson($aRow[0]);
			}
		}
		else
		{
			// Reset previous members' family ID to 0 (undefined)
			$sSQL = "UPDATE person_per SET per_fam_ID = 0 WHERE per_fam_ID = " . $iFamilyID;
			RunQuery($sSQL);
		}

		// Delete the specified Family record
		$sSQL = "DELETE FROM family_fam WHERE fam_ID = " . $iFamilyID;
		RunQuery($sSQL);

		// Remove custom field data
		$sSQL = "DELETE FROM family_custom WHERE fam_ID = " . $iFamilyID;
		RunQuery($sSQL);

		// Delete the photo files, if they exist
		$photoThumbnail = "Images/Family/thumbnails/" . $iFamilyID . ".jpg";
		if (file_exists($photoThumbnail))
			unlink ($photoThumbnail);
		$photoFile = "Images/Family/" . $iFamilyID . ".jpg";
		if (file_exists($photoFile))
			unlink ($photoFile);

		// Redirect back to the family listing
		Redirect("SelectList.php?mode=family");
	}
}

if($sMode == 'person')
{
	// Get the data on this person
	$sSQL = "SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID = " . $iPersonID;
	$rsPerson = RunQuery($sSQL);
	extract(mysql_fetch_array($rsPerson));

	// See if this person is a user
	$sSQL = "SELECT '' FROM user_usr WHERE usr_per_ID = " . $iPersonID;
	$rsUser = RunQuery($sSQL);
	$bIsUser = (mysql_num_rows($rsUser) > 0);
}
else
{
	//Get the family record in question
	$sSQL = "SELECT * FROM family_fam WHERE fam_ID = " . $iFamilyID;
	$rsFamily = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFamily));
}

require "Include/Header.php";

if($sMode == 'person')
{

	if ($bIsUser) {
		echo "<p class=\"LargeText\">" . gettext("Sorry, this person is a user.  An administrator must remove their user status before they may be deleted from the database.") . "<br><br>";
		echo "<a href=\"PersonView.php?PersonID=" . $iPersonID . ">" . gettext("Return to Person View") . "</a></p>";
	}
	else
	{
		echo "<p>" . gettext("Please confirm deletion of:") . "</p>";
		echo "<p class=\"ShadedBox\">" . $per_FirstName . " " . $per_LastName . "</p>";
		echo "<BR>";
		echo "<p><h3><a href=\"SelectDelete.php?mode=person&PersonID=" . $iPersonID . "&Confirmed=Yes\">" . gettext("Yes, delete this record") . "</a>" . gettext(" (This action CANNOT be undone!)") . "</h3></p>";
		echo "<p><h2><a href=\"PersonView.php?PersonID=" . $iPersonID . "\">" . gettext("No, cancel this deletion") . "</a></h2></p>";
	}
}
else
{
	// Delete Family Confirmation
	// See if this family has any donations
	$sSQL = "SELECT plg_plgID FROM pledge_plg WHERE plg_PledgeOrPayment = 'Payment' AND plg_FamID = " . $iFamilyID;
	$rsDonations = RunQuery($sSQL);
	$bIsDonor = (mysql_num_rows($rsDonations) > 0);
	if ($bIsDonor && !$_SESSION['bFinance']) {
		// Donations from Family. Current user not authorized for Finance
		echo "<p class=\"LargeText\">" . gettext("Sorry, there are records of donations from this family. This family may not be deleted.") . "<br><br>";
		echo "<a href=\"FamilyView.php?FamilyID=" . $iFamilyID . "\">" . gettext("Return to Family View") . "</a></p>";
	
	} elseif ($bIsDonor && $_SESSION['bFinance']) {
		// Donations from Family. Current user authorized for Finance. 
		// Select another family to move donations to.
		echo "<p class=\"LargeText\">" . gettext("WARNING: This family has records of donations and may NOT be deleted until these donations are associated with another family.") . "</p>";
		echo "<form name=SelectFamily method=get action=SelectDelete.php>";
		echo "<div class=\"ShadedBox\">";
		echo "<div class=\"LightShadedBox\"><strong>" . gettext("Family Name:") . " $fam_Name</strong></div>";
		echo "<p>" . gettext("Please select another family with whom to associate these donations:");
		echo "<br><b>".gettext("WARNING: This action can not be undone and may have legal implications!")."</b></p>";
		echo "<input name=FamilyID value=$iFamilyID type=hidden>";
		echo "<select name=DonationFamilyID><option value=0 selected>". gettext("Unassigned") ."</option>";
		
		//Get Families for the drop-down
		$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam ORDER BY fam_Name";
		$rsFamilies = RunQuery($sSQL);
		// Build Criteria for Head of Household
		if (!$sDirRoleHead)
			$sDirRoleHead = "1";
		$head_criteria = " per_fmr_ID = " . $sDirRoleHead;
		// If more than one role assigned to Head of Household, add OR
		$head_criteria = str_replace(",", " OR per_fmr_ID = ", $head_criteria);
		// Add Spouse to criteria
		if (intval($sDirRoleSpouse) > 0)
			$head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
		// Build array of Head of Households and Spouses with fam_ID as the key
		$sSQL = "SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (" . $head_criteria . ") ORDER BY per_fam_ID";
		$rs_head = RunQuery($sSQL);
		$aHead = "";
		while (list ($head_firstname, $head_famid) = mysql_fetch_row($rs_head)){
			if ($head_firstname && $aHead[$head_famid])
				$aHead[$head_famid] .= " & " . $head_firstname;
			elseif ($head_firstname)
				$aHead[$head_famid] = $head_firstname;
		}
		while ($aRow = mysql_fetch_array($rsFamilies)){
			extract($aRow);
			echo "<option value=\"" . $fam_ID . "\"";
			if ($fam_ID == $iFamilyID) { echo " selected"; }
			echo ">" . $fam_Name;
			if ($aHead[$fam_ID])
				echo ", " . $aHead[$fam_ID];
			if ($fam_ID == $iFamilyID)
				echo " -- " . gettext("CURRENT FAMILY WITH DONATIONS");
			else
				echo " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
		}
		echo "</select><br><br>";
		echo "<input type=submit name=CancelFamily value=\"Cancel and Return to Family View\"> &nbsp; &nbsp; ";
		echo "<input type=submit name=MoveDonations value=\"Move Donations to Selected Family\">";
		echo "</div></form>";
		
		// Show payments connected with family
		// -----------------------------------
		echo "<br><br>";
		//Get the pledges for this family
		$sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, 
		         plg_comment, plg_DateLastEdited, plg_PledgeOrPayment, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, b.fun_Name AS fundName
				 FROM pledge_plg 
				 LEFT JOIN person_per a ON plg_EditedBy = a.per_ID
				 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
				 WHERE plg_famID = " . $iFamilyID . " ORDER BY pledge_plg.plg_date";
		$rsPledges = RunQuery($sSQL);
		?>
		<table cellpadding="5" cellspacing="0" width="100%">
			<tr class="TableHeader">
			<td><?php echo gettext("Type"); ?></td>
			<td><?php echo gettext("Fund"); ?></td>
			<td><?php echo gettext("Fiscal Year"); ?></td>
			<td><?php echo gettext("Date"); ?></td>
			<td><?php echo gettext("Amount"); ?></td>
			<td><?php echo gettext("Schedule"); ?></td>
			<td><?php echo gettext("Method"); ?></td>
			<td><?php echo gettext("Comment"); ?></td>
			<td><?php echo gettext("Date Updated"); ?></td>
			<td><?php echo gettext("Updated By"); ?></td>
		</tr>
		<?php
		$tog = 0;
		//Loop through all pledges
		while ($aRow =mysql_fetch_array($rsPledges)){
			$tog = (! $tog);
			$plg_FYID = "";
			$plg_date = "";
			$plg_amount = "";
			$plg_schedule = "";
			$plg_method = "";
			$plg_comment = "";
			$plg_plgID = 0;
			$plg_DateLastEdited  = "";
			$plg_EditedBy = "";
			extract($aRow);

			//Alternate the row style
			if ($tog)
				$sRowClass = "RowColorA";
			else
				$sRowClass = "RowColorB";

			if ($plg_PledgeOrPayment == 'Payment') {
				if ($tog)
					$sRowClass = "PaymentRowColorA";
				else
					$sRowClass = "PaymentRowColorB";
			}
			?>
			<tr class="<?php echo $sRowClass ?>">
				<td><?php echo $plg_PledgeOrPayment ?>&nbsp;</td>
				<td><?php echo $fundName ?>&nbsp;</td>
				<td><?php echo MakeFYString ($plg_FYID) ?>&nbsp;</td>
				<td><?php echo $plg_date ?>&nbsp;</td>
				<td><?php echo $plg_amount ?>&nbsp;</td>
				<td><?php echo $plg_schedule ?>&nbsp;</td>
				<td><?php echo $plg_method; ?>&nbsp;</td>
				<td><?php echo $plg_comment; ?>&nbsp;</td>
				<td><?php echo $plg_DateLastEdited; ?>&nbsp;</td>
				<td><?php echo $EnteredFirstName . " " . $EnteredLastName; ?>&nbsp;</td>
			</tr>
			<?php
		}
		echo "</table>";

	
	} else {
		// No Donations from family.  Normal delete confirmation
		echo $DonationMessage;
		echo "<p>" . gettext("Please confirm deletion of this family record:") . "</p>";
		echo "<p>" . gettext("Note: This will also delete all Notes associated with this Family record.") . "</p>";
		echo "<div class=\"ShadedBox\">";
		echo "<div class=\"LightShadedBox\"><strong>" . gettext("Family Name:") . "</strong></div>";
		echo "&nbsp;" . $fam_Name;
		echo "</div>";
		echo "<p class=\"MediumText\"><a href=\"SelectDelete.php?Confirmed=Yes&FamilyID=" . $iFamilyID . "\">" . gettext("Delete Family Record ONLY") . "</a>" . gettext(" (this action cannot be undone)") . "</p>";
		echo "<div class=\"ShadedBox\">";
		echo "<div class=\"LightShadedBox\"><strong>" . gettext("Family Members:") . "</strong></div>";
		//List Family Members
		$sSQL = "SELECT * FROM person_per WHERE per_fam_ID = " . $iFamilyID;
		$rsPerson = RunQuery($sSQL);
		while($aRow = mysql_fetch_array($rsPerson)) {
			extract($aRow);
			echo "&nbsp;" . $per_FirstName . " " . $per_LastName . "<br>";
			RunQuery($sSQL);
		}
		echo "</div>";
		echo "<p class=\"MediumText\"><a href=\"SelectDelete.php?Confirmed=Yes&Members=Yes&FamilyID=" . $iFamilyID . "\">" . gettext("Delete Family Record AND Family Members") . "</a>" . gettext(" (this action cannot be undone)") . "</p>";
		echo "<br><p class=\"LargeText\"><a href=\"FamilyView.php?FamilyID=".$iFamilyID."\">" . gettext("No, cancel this deletion</a>") . "</p>";
	}
}
require "Include/Footer.php";
?>
