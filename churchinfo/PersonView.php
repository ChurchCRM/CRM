<?php
/*******************************************************************************
 *
 *  filename    : PersonView.php
 *  last change : 2003-04-14
 *  description : Displays all the information about a single person
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Get the person ID from the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

$iRemoveVO = FilterInput($_GET["RemoveVO"],'int');

if ( isset($_POST["GroupAssign"]) && $_SESSION['bManageGroups'] )
{
	$iGroupID = FilterInput($_POST["GroupAssignID"],'int');
	AddToGroup($iPersonID,$iGroupID,0);
}

if ( isset($_POST["VolunteerOpportunityAssign"]))
{
	$iVolunteerOpportunityID = FilterInput($_POST["VolunteerOpportunityID"],'int');
	AddVolunteerOpportunity($iPersonID,$iVolunteerOpportunityID);
}

// Service remove-volunteer-opportunity (these links set RemoveVO)
if ($iRemoveVO > 0)
{
	RemoveVolunteerOpportunity($iPersonID, $iRemoveVO);
}

$dSQL= "SELECT per_ID FROM person_per order by per_LastName, per_FirstName";
$dResults = RunQuery($dSQL);

$last_id = 0;
$next_id = 0;
$capture_next = 0;
while($myrow = mysql_fetch_row($dResults))
{
	$pid = $myrow[0];
	if ($capture_next == 1)
	{
	    $next_id = $pid;
		break;
	}
	if ($pid == $iPersonID)
	{
		$previous_id = $last_id;
		$capture_next = 1;
	}
	$last_id = $pid;
}

if (($previous_id > 0)) {
    $previous_link_text = "<a class=\"SmallText\" href=\"PersonView.php?PersonID=$previous_id\">" . gettext("Previous Person") . "</a>";
}

if (($next_id > 0)) {
    $next_link_text = "<a class=\"SmallText\" href=\"PersonView.php?PersonID=$next_id\">" . gettext("Next Person") . "</a>";
}

// Get this person's data
$sSQL = "SELECT a.*, family_fam.*, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole, b.per_FirstName AS EnteredFirstName,
				b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName, c.per_LastName AS EditedLastName
			FROM person_per a
			LEFT JOIN family_fam ON a.per_fam_ID = family_fam.fam_ID
			LEFT JOIN list_lst cls ON a.per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
			LEFT JOIN list_lst fmr ON a.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
			LEFT JOIN person_per b ON a.per_EnteredBy = b.per_ID
			LEFT JOIN person_per c ON a.per_EditedBy = c.per_ID
			WHERE a.per_ID = " . $iPersonID;
$rsPerson = RunQuery($sSQL);
extract(mysql_fetch_array($rsPerson));

// Get the lists of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master
			WHERE custom_Side = 'left' ORDER BY custom_Order";
$rsLeftCustomFields = RunQuery($sSQL);

$sSQL = "SELECT person_custom_master.* FROM person_custom_master
			WHERE custom_Side = 'right' ORDER BY custom_Order";
$rsRightCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
$sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $iPersonID;
$rsCustomData = RunQuery($sSQL);
$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);

// Get the notes for this person
$sSQL = "SELECT nte_Private, nte_ID, nte_Text, nte_DateEntered, nte_EnteredBy, nte_DateLastEdited, nte_EditedBy, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName ";
$sSQL .= "FROM note_nte ";
$sSQL .= "LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID ";
$sSQL .= "LEFT JOIN person_per b ON nte_EditedBy = b.per_ID ";
$sSQL .= "WHERE nte_per_ID = " . $iPersonID;

// Admins should see all notes, private or not.  Otherwise, only get notes marked non-private or private to the current user.
if (!$_SESSION['bAdmin'])
	$sSQL .= " AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";

$rsNotes = RunQuery($sSQL);

// Get the Groups this Person is assigned to
$sSQL = "SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
		FROM group_grp
		LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
		WHERE person2group2role_p2g2r.p2g2r_per_ID = " . $iPersonID . "
		ORDER BY grp_Name";
$rsAssignedGroups = RunQuery($sSQL);

// Get all the Groups
$sSQL = "SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Get the volunteer opportunities this Person is assigned to
$sSQL = "SELECT vol_ID, vol_Name, vol_Description FROM volunteeropportunity_vol
		LEFT JOIN person2volunteeropp_p2vo ON p2vo_vol_ID = vol_ID
		WHERE person2volunteeropp_p2vo.p2vo_per_ID = " . $iPersonID;
$rsAssignedVolunteerOpps = RunQuery($sSQL);

// Get all the volunteer opportunities
$sSQL = "SELECT vol_ID, vol_Name FROM volunteeropportunity_vol ORDER BY vol_Name";
$rsVolunteerOpps = RunQuery($sSQL);

// Get the Properties assigned to this Person
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'p' AND r2p_record_ID = " . $iPersonID .
		" ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);

// Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'p' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

$dBirthDate = FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay);

$sFamilyInfoBegin = "<span style=\"color: red;\">";
$sFamilyInfoEnd = "</span>";

// Assign the values locally, after selecting whether to display the family or person information

SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, True);
$sCity = SelectWhichInfo($per_City, $fam_City, True);
$sState = SelectWhichInfo($per_State, $fam_State, True);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, True);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, True);
$sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, False);
$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), True);
$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), True);
$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), True);
$sEmail = SelectWhichInfo($per_Email, $fam_Email, True);
$sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, False);

if ($per_Envelope > 0)
	$sEnvelope = $per_Envelope;
else
	$sEnvelope = gettext("Not assigned");

// Set the page title and include HTML header
$sPageTitle = gettext("Person View");
require "Include/Header.php";

$iTableSpacerWidth = 10;

$bOkToEdit = ($_SESSION['bEditRecords'] ||
			  ($_SESSION['bEditSelf'] && $per_ID==$_SESSION['iUserID']) ||
			  ($_SESSION['bEditSelf'] && $per_fam_ID==$_SESSION['iFamID'])
			 );   

if ($previous_link_text) {
	echo "$previous_link_text | ";
}
if ($bOkToEdit) { 
	echo "<a class=\"SmallText\" href=\"PersonEditor.php?PersonID=" . $per_ID . 
		 "\">" . gettext("Edit this Record") . "</a> | "; 
}

if ($_SESSION['bDeleteRecords']) { echo "<a class=\"SmallText\" href=\"SelectDelete.php?mode=person&PersonID=" . $per_ID . "\">" . gettext("Delete this Record") . "</a> | " ; }
?>
<a href="PrintView.php?PersonID=<?php echo $per_ID; ?>" class="SmallText"><?php echo gettext("Printable Page"); ?></a>
| <a href="PersonView.php?PersonID=<?php echo $per_ID; ?>&AddToPeopleCart=<?php echo $per_ID; ?>" class="SmallText"><?php echo gettext("Add to Cart"); ?></a>

<?php
if ($_SESSION['bAdmin'])
{
	$sSQL = "SELECT usr_per_ID FROM user_usr WHERE usr_per_ID = " . $per_ID;
	if (mysql_num_rows(RunQuery($sSQL)) == 0)
		echo " | <a class=\"SmallText\" href=\"UserEditor.php?NewPersonID=" . $per_ID . "\">" . gettext("Make User") . "</a>" ;
	else
		echo " | <a class=\"SmallText\" href=\"UserEditor.php?PersonID=" . $per_ID . "\">" . gettext("Edit User") . "</a>" ;
}

if ($next_link_text) {
	echo " | $next_link_text";
}

?>

<br><br>
<table border="0" width="100%" cellspacing="0" cellpadding="4">
<tr>
<td width="25%" valign="top" align="center">
	<div class="LightShadedBox">
	<?php
		echo "<font size=\"4\"><b>";
		echo FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0);
		echo "</font></b><br>";

		if ($fam_ID != "") {
			echo "<font size=\"2\">(";
			if ($sFamRole != "") { echo $sFamRole; } else { echo gettext("Member"); }
			echo gettext(" of the") . " <a href=\"FamilyView.php?FamilyID=" . $fam_ID . "\">" . $fam_Name . "</a>" . gettext(" family)") . "</font><br><br>";
		}
		else
			echo gettext("(No assigned family)") . "<br><br>";

		echo "<div class=\"TinyShadedBox\">";
			echo "<font size=\"3\">";
			if ($sAddress1 != "") { echo $sAddress1 . "<br>"; }
			if ($sAddress2 != "") { echo $sAddress2 . "<br>"; }
			if ($sCity != "") { echo $sCity . ", "; }
			if ($sState != "") { echo $sState; }
			if ($sZip != "") { echo " " . $sZip; }
			if ($sCountry != "") {echo "<br>" . $sCountry; }
			echo "</font>";
		echo "</div>";

		// Strip tags in case they were added for family inherited data
		$sAddress1 = strip_tags($sAddress1);
		$sCity = strip_tags($sCity);
		$sState = strip_tags($sState);
		$sCountry = strip_tags($sCountry);

		//Show link to mapquest
		if ($sAddress1 != "" && $sCity != "" && $sState != "")
		{
			if ($sCountry == "United States") {
				$sMQcountry = "";
				$bShowMQLink = true;
			}
			elseif ($sCountry == "Canada") {
				$sMQcountry = "country=CA&";
				$bShowMQLink = true;
			}
			else
				$bShowMQLink = false;
		}

		if ($bShowMQLink)
			echo "<div align=\"right\"><a class=\"SmallText\" target=\"_blank\" href=\"http://www.mapquest.com/maps/map.adp?" .$sMQcountry . "city=" . urlencode($sCity) . "&state=" . $sState . "&address=" . urlencode($sAddress1) . "\">" . gettext("View Map") . "</a></div>";

		echo "<br>";

		// Upload photo
		if ( isset($_POST["UploadPhoto"]) && ($_SESSION['bAddRecords'] || $bOkToEdit) ) {
			if ($_FILES['Photo']['name'] == "") {
				$PhotoError = gettext("No photo selected for uploading.");
			} elseif ($_FILES['Photo']['type'] != "image/pjpeg" && $_FILES['Photo']['type'] != "image/jpeg") {
				$PhotoError = gettext("Only jpeg photos can be uploaded.");
			} else {
				// Create the thumbnail used by PersonView

            chmod ($_FILES['Photo']['tmp_name'], 0777);

				$srcImage=imagecreatefromjpeg($_FILES['Photo']['tmp_name']);
				$src_w=imageSX($srcImage);
    			$src_h=imageSY($srcImage);

				// Calculate thumbnail's height and width (a "maxpect" algorithm)
				$dst_max_w = 200;
				$dst_max_h = 350;
				if ($src_w > $dst_max_w) {
					$thumb_w=$dst_max_w;
					$thumb_h=$src_h*($dst_max_w/$src_w);
					if ($thumb_h > $dst_max_h) {
						$thumb_h = $dst_max_h;
						$thumb_w = $src_w*($dst_max_h/$src_h);
					}
				}
				elseif ($src_h > $dst_max_h) {
					$thumb_h=$dst_max_h;
					$thumb_w=$src_w*($dst_max_h/$src_h);
					if ($thumb_w > $dst_max_w) {
						$thumb_w = $dst_max_w;
						$thumb_h = $src_h*($dst_max_w/$src_w);
					}
				}
				else {
					if ($src_w > $src_h) {
						$thumb_w = $dst_max_w;
						$thumb_h = $src_h*($dst_max_w/$src_w);
					} elseif ($src_w < $src_h) {
						$thumb_h = $dst_max_h;
						$thumb_w = $src_w*($dst_max_h/$src_h);
					} else {
						if ($dst_max_w >= $dst_max_h) {
							$thumb_w=$dst_max_h;
							$thumb_h=$dst_max_h;
						} else {
							$thumb_w=$dst_max_w;
							$thumb_h=$dst_max_w;
						}
					}
				}
				$dstImage=ImageCreateTrueColor($thumb_w,$thumb_h);
        		imagecopyresampled($dstImage,$srcImage,0,0,0,0,$thumb_w,$thumb_h,$src_w,$src_h);
				imagejpeg($dstImage, "Images/Person/thumbnails/" . $iPersonID . ".jpg");
				imagedestroy($dstImage);
    			imagedestroy($srcImage);
				move_uploaded_file($_FILES['Photo']['tmp_name'], "Images/Person/" . $iPersonID . ".jpg");
			}
		} elseif (isset($_POST["DeletePhoto"]) && $_SESSION['bDeleteRecords']) {
			unlink("Images/Person/" . $iPersonID . ".jpg");
			unlink("Images/Person/thumbnails/" . $iPersonID . ".jpg");
		}

		// Display photo or upload from file
		$photoFile = "Images/Person/thumbnails/" . $iPersonID . ".jpg";
        if (file_exists($photoFile))
        {
            echo '<a target="_blank" href="Images/Person/' . $iPersonID . '.jpg">';
            echo '<img border="1" src="'.$photoFile.'"></a>';
            if ($bOkToEdit) {
                echo '
                    <form method="post"
                    action="PersonView.php?PersonID=' . $iPersonID . '">
                    <br>
                    <input type="submit" class="icTinyButton" 
                    value="' . gettext("Delete Photo") . '" name="DeletePhoto">
                    </form>';
                }
        } else {
            // Some old / M$ browsers can't handle PNG's correctly.
            if ($bDefectiveBrowser)
                echo '<img border="0" src="Images/NoPhoto.gif"><br><br><br>';
            else
                echo '<img border="0" src="Images/NoPhoto.png"><br><br><br>';

            if ($bOkToEdit) {
                if (isset($PhotoError))
                    echo '<span style="color: red;">' . $PhotoError . '</span><br>';

                echo '
                    <form method="post" 
                    action="PersonView.php?PersonID=' . $iPersonID . '" 
                    enctype="multipart/form-data">
                    <input class="icTinyButton" type="file" name="Photo">
                    <input type="submit" class="icTinyButton" 
                    value="' . gettext("Upload Photo") . '" name="UploadPhoto">
                    </form>';
            }
        }
	?>
	</div>
</td>

<td width="75%" valign="top" align="left">

	<b><?php echo gettext("General Information:"); ?></b>
	<div class="LightShadedBox">
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td align="center">
			<table cellspacing="4" cellpadding="0" border="0">
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Gender:"); ?></td>
				<td class="TinyTextColumn">
				<?php
					switch ($per_Gender)
					{
					case 1:
						echo gettext("Male");
						break;
					case 2:
						echo gettext("Female");
						break;
					}
				?>
				</td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Birthdate:"); ?></td>
				<td class="TinyTextColumn"><?php echo $dBirthDate; ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Age:"); ?></td>
				<td class="TinyTextColumn"><?php PrintAge($per_BirthMonth,$per_BirthDay,$per_BirthYear,$per_Flags); ?></td>
			</tr>
<?php if (!$bHideFriendDate) { /* Friend Date can be hidden - General Settings */ ?>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Friend Date:"); ?></td>
				<td class="TinyTextColumn"><?php echo FormatDate($per_FriendDate,false); ?></td>
			</tr>
<?php } ?>	
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Membership Date:"); ?></td>
				<td class="TinyTextColumn"><?php echo FormatDate($per_MembershipDate,false); ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Classification:"); ?></td>
				<td class="TinyTextColumn"><?php echo $sClassName; ?></td>
			</tr>
			<?php
				// Display the left-side custom fields
				while ($Row = mysql_fetch_array($rsLeftCustomFields)) {
					extract($Row);
					$currentData = trim($aCustomData[$custom_Field]);
					if ($type_ID == 11) $custom_Special = $sPhoneCountry;
					echo "<tr><td class=\"TinyLabelColumn\">" . $custom_Name . "</td>";
					echo "<td class=\"TinyTextColumn\">" . displayCustomField($type_ID, $currentData, $custom_Special) . "</td></tr>";
				}
			?>
			</table>
		</td>
		<td align="center">
			<table cellspacing="4" cellpadding="0" border="0">
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Home Phone:"); ?></td>
				<td class="TinyTextColumn"><?php echo $sHomePhone; ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Work Phone:"); ?></td>
				<td class="TinyTextColumn"><?php echo $sWorkPhone; ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Mobile Phone:"); ?></td>
				<td class="TinyTextColumn"><?php echo $sCellPhone; ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Email:"); ?></td>
				<td class="TinyTextColumn"><?php if ($sEmail != "") { echo "<a href=\"mailto:" . $sUnformattedEmail . "\">" . $sEmail . "</a>"; } ?></td>
			</tr>
			<tr>
				<td class="TinyLabelColumn"><?php echo gettext("Work/Other Email:"); ?></td>
				<td class="TinyTextColumn"><?php if ($per_WorkEmail != "") { echo "<a href=\"mailto:" . $per_WorkEmail . "\">" . $per_WorkEmail . "</a>"; } ?></td>
			</tr>
			<?php
				// Display the right-side custom fields
				while ($Row = mysql_fetch_array($rsRightCustomFields)) {
					extract($Row);
					$currentData = trim($aCustomData[$custom_Field]);
					if ($type_ID == 11) $custom_Special = $sPhoneCountry;
					echo "<tr><td class=\"TinyLabelColumn\">" . $custom_Name . "</td>";
					echo "<td class=\"TinyTextColumn\">" . displayCustomField($type_ID, $currentData, $custom_Special) . "</td></tr>";
				}
			?>
			</table>
		</td>
	</tr>
	</table>
	</div>
	<br>
	<b><?php echo gettext("Assigned Properties:"); ?></b>

	<?php

	$sAssignedProperties = ",";

	//Was anything returned?
	if (mysql_num_rows($rsAssignedProperties) == 0)
	{
		echo "<p align\"center\">" . gettext("No property assignments.") . "</p>";
	}
	else
	{
		//Yes, start the table
		echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
		echo "<tr class=\"TableHeader\">";
		echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Type") . "</b>";
		echo "<td width=\"15%\" valign=\"top\"><b>" . gettext("Name") . "</b>";
		echo "<td valign=\"top\"><b>" . gettext("Value") . "</b></td>";

		if ($bOkToEdit)
		{
			echo "<td valign=\"top\"><b>" . gettext("Edit") . "</b></td>";
			echo "<td valign=\"top\"><b>" . gettext("Remove") . "</b></td>";
		}
		echo "</tr>";

		$last_pro_prt_ID = "";
		$bIsFirst = true;

		//Loop through the rows
		while ($aRow = mysql_fetch_array($rsAssignedProperties))
		{
			$pro_Prompt = "";
			$r2p_Value = "";

			extract($aRow);

			if ($pro_prt_ID != $last_pro_prt_ID)
			{
				echo "<tr class=\"";
				if ($bIsFirst)
					echo "RowColorB";
				else
					echo "RowColorC";
				echo "\"><td><b>" . $prt_Name . "</b></td>";

				$bIsFirst = false;
				$last_pro_prt_ID = $pro_prt_ID;
				$sRowClass = "RowColorB";
			}
			else
			{
				echo "<tr class=\"" . $sRowClass . "\">";
				echo "<td valign=\"top\">&nbsp;</td>";
			}

			echo "<td valign=\"center\">" . $pro_Name . "&nbsp;</td>";
			echo "<td valign=\"center\">" . $r2p_Value . "&nbsp;</td>";

			if ($bOkToEdit)
			{
				if (strlen($pro_Prompt) > 0)
				{
					echo "<td valign=\"center\"><a href=\"PropertyAssign.php?PersonID=" . $iPersonID . "&PropertyID=" . $pro_ID . "\">" . gettext("Edit") . "</a></td>";
				}
				else
				{
					echo "<td>&nbsp;</td>";
				}
				echo "<td valign=\"center\"><a href=\"PropertyUnassign.php?PersonID=" . $iPersonID . "&PropertyID=" . $pro_ID . "\">" . gettext("Remove") . "</a></td>";
			}
			echo "</tr>";

			//Alternate the row style
			$sRowClass = AlternateRowStyle($sRowClass);

			$sAssignedProperties .= $pro_ID . ",";
		}
		echo "</table>";
	}

	?>

	<?php if ($bOkToEdit) { ?>
	<form method="post" action="PropertyAssign.php?PersonID=<?php echo $iPersonID; ?>">
	<p class="SmallText" align="center">
		<span class="SmallText"><?php echo gettext("Assign a New Property:"); ?></span>
		<select name="PropertyID">
			<?php
			while ($aRow = mysql_fetch_array($rsProperties))
			{
				extract($aRow);

				//If the property doesn't already exist for this Person, write the <OPTION> tag
				if (strlen(strstr($sAssignedProperties,"," . $pro_ID . ",")) == 0)
				{
					echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
				}
			}
			?>
		</select>
		<input type="submit" class="icButton" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="Submit" style="font-size: 8pt;">
	</p>
	</form>
	<?php }
	else
	{
		echo "<br><br><br>";
	}
	?>

	<b><?php echo gettext("Assigned Groups:"); ?></b>

	<script language="javascript">
	function GroupRemove( Group, Person ) {
	var answer = confirm (<?php echo "'" . gettext("Warning: If you remove this group membership, you will irrevokably lose any member data assigned") . "'"; ?>)
	if ( answer )
		window.location="GroupMemberList.php?GroupID=" + Group + "&PersonToRemove=" + Person
	}
	</script>

	<?php

	//Initialize row shading
	$sRowClass = "RowColorA";

	$sAssignedGroups = ",";

	//Was anything returned?
	if (mysql_num_rows($rsAssignedGroups) == 0)
	{
		echo "<p align=\"center\">" . gettext("No group assignments.") . "</p>";
	}
	else
	{
		echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
		echo "<tr class=\"TableHeader\">";
		echo "<td>" . gettext("Group Name") . "</td>";
		echo "<td>" . gettext("Role") . "</td>";
		if ($_SESSION['bManageGroups'])
		{
			echo "<td>" . gettext("Properties") . "</td>";
			echo "<td width=\"10%\">" . gettext("Remove") . "</td>";
		}
		echo "</tr>";

		// Loop through the rows
		while ($aRow = mysql_fetch_array($rsAssignedGroups))
		{
			extract($aRow);

			// Alternate the row style
			$sRowClass = AlternateRowStyle($sRowClass);

			echo "<tr class=\"" . $sRowClass . "\">";
			echo "<td><a href=\"GroupView.php?GroupID=" . $grp_ID . "\">" . $grp_Name . "</a></td>";

			echo "<td>";
			if ($_SESSION['bManageGroups']) echo "<a href=\"MemberRoleChange.php?GroupID=" . $grp_ID . "&PersonID=" . $iPersonID . "\">";
			echo $roleName;
			if ($_SESSION['bManageGroups']) echo "</a>";
			echo "</td>";

			if ($_SESSION['bManageGroups']) {
				if ($grp_hasSpecialProps == 'true')
					echo "<td><a href=\"GroupPropsEditor.php?PersonID=" . $iPersonID . "&GroupID=" . $grp_ID . "\">" . gettext("View/Edit") . "</a></td>";
				else
					echo "<td>&nbsp;</td>";
				echo "<td><input type=\"button\" class=\"icTinyButton\" value=\"" . gettext("Remove") . "\" Name=\"remove\" onclick=\"GroupRemove(" . $grp_ID . ", " . $iPersonID . ");\" ></td>";
			}
			echo "</tr>";

			// If this group has associated special properties, display those with values and prop_PersonDisplay flag set.
			if ($grp_hasSpecialProps == 'true')
			{
				$firstRow = true;
				// Get the special properties for this group
				$sSQL = "SELECT groupprop_master.* FROM groupprop_master
					WHERE grp_ID = " . $grp_ID . " AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
				$rsPropList = RunQuery($sSQL);

				$sSQL = "SELECT * FROM groupprop_" . $grp_ID . " WHERE per_ID = " . $iPersonID;
				$rsPersonProps = RunQuery($sSQL);
				$aPersonProps = mysql_fetch_array($rsPersonProps, MYSQL_BOTH);

				while ($aProps = mysql_fetch_array($rsPropList))
				{
					extract($aProps);
					$currentData = trim($aPersonProps[$prop_Field]);
					if (strlen($currentData) > 0)
					{
						// only create the properties table if it's actually going to be used
						if ($firstRow) {
							echo "<tr><td colspan=\"2\"><table width=\"100%\"><tr><td width=\"15%\"></td><td><table width=\"90%\" cellspacing=\"0\">";
							echo "<tr class=\"TinyTableHeader\"><td>" . gettext("Property") . "</td><td>" . gettext("Value") . "</td></tr>";
							$firstRow = false;
						}
						$sRowClass = AlternateRowStyle($sRowClass);
						if ($type_ID == 11) $prop_Special = $sPhoneCountry;
						echo "<tr class=\"$sRowClass\"><td>" . $prop_Name . "</td><td>" . displayCustomField($type_ID, $currentData, $prop_Special) . "</td></tr>";
					}
				}
				if (!$firstRow) echo "</table></td></tr></table></td></tr>";
			}

			// NOTE: this method is crude.  Need to replace this with use of an array.
			$sAssignedGroups .= $grp_ID . ",";
		}
		echo "</table>";
	}
	?>

	<?php if ($_SESSION['bManageGroups']) { ?>
	<form method="post" action="PersonView.php?PersonID=<?php echo $iPersonID ?>">
	<p class="SmallText" align="center">
		<span class="SmallText"><?php echo gettext("Assign a New Group:"); ?></span>
		<select name="GroupAssignID">
			<?php
			while ($aRow = mysql_fetch_array($rsGroups))
			{
				extract($aRow);

				//If the property doesn't already exist for this Person, write the <OPTION> tag
				if (strlen(strstr($sAssignedGroups,"," . $grp_ID . ",")) == 0)
				{
					echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
				}
			}
			?>
		</select>
		<input type="submit" class="icButton" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="GroupAssign" style="font-size: 8pt;">
		<br>
		<span class="SmallText" align="center"><?php echo gettext("(Person will be assigned to the Group in the Default Role.)"); ?></span>
	</p>
	</form>
	<?php } ?>


	<b><?php echo gettext("Volunteer opportunities:"); ?></b>

	<?php

	//Initialize row shading
	$sRowClass = "RowColorA";

	$sAssignedVolunteerOpps = ",";

	//Was anything returned?
	if (mysql_num_rows($rsAssignedVolunteerOpps) == 0)
	{
		echo "<p align=\"center\">" . gettext("No volunteer opportunity assignments.") . "</p>";
	}
	else
	{
		echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
		echo "<tr class=\"TableHeader\">";
		echo "<td>" . gettext("Name") . "</td>";
		echo "<td>" . gettext("Description") . "</td>";
		echo "<td width=\"10%\">" . gettext("Remove") . "</td>";
		echo "</tr>";

		// Loop through the rows
		while ($aRow = mysql_fetch_array($rsAssignedVolunteerOpps))
		{
			extract($aRow);

			// Alternate the row style
			$sRowClass = AlternateRowStyle($sRowClass);

			echo "<tr class=\"" . $sRowClass . "\">";
			echo "<td>" . $vol_Name . "</a></td>";
			echo "<td>" . $vol_Description . "</a></td>";

			echo "<td><a class=\"SmallText\" href=\"PersonView.php?PersonID=" . $per_ID . "&RemoveVO=" . $vol_ID . "\">" . gettext("Remove") . "</a></td>";

			echo "</tr>";

			// NOTE: this method is crude.  Need to replace this with use of an array.
			$sAssignedVolunteerOpps .= $vol_ID . ",";
		}
		echo "</table>";
	}
	?>

	<form method="post" action="PersonView.php?PersonID=<?php echo $iPersonID ?>">
	<p class="SmallText" align="center">
		<span class="SmallText"><?php echo gettext("Assign a New Volunteer Opportunity:"); ?></span>
		<select name="VolunteerOpportunityID">
			<?php
			while ($aRow = mysql_fetch_array($rsVolunteerOpps))
			{
				extract($aRow);

				//If the property doesn't already exist for this Person, write the <OPTION> tag
				if (strlen(strstr($sAssignedVolunteerOpps,"," . $vol_ID . ",")) == 0)
				{
					echo "<option value=\"" . $vol_ID . "\">" . $vol_Name . "</option>";
				}
			}
			?>
		</select>
		<input type="submit" class="icButton" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="VolunteerOpportunityAssign" style="font-size: 8pt;">
		<br>
	</p>
	</form>
</td>
</tr>
</table>

</td>
</tr>
</table>

<?php
if ($previous_link_text) {
	echo "$previous_link_text | ";
}
if ($bOkToEdit) { echo "<a class=\"SmallText\" href=\"PersonEditor.php?PersonID=" . $per_ID . "\">" . gettext("Edit this Record") . "</a> | "; }
if ($_SESSION['bDeleteRecords']) { echo "<a class=\"SmallText\" href=\"SelectDelete.php?mode=person&PersonID=" . $per_ID . "\">" . gettext("Delete this Record") . "</a> | " ; }
?>
<a href="PrintView.php?PersonID=<?php echo $per_ID; ?>" class="SmallText"><?php echo gettext("Printable Page"); ?></a>
| <a href="PersonView.php?PersonID=<?php echo $per_ID; ?>&AddToPeopleCart=<?php echo $per_ID; ?>" class="SmallText"><?php echo gettext("Add to Cart"); ?></a>

<?php
if ($_SESSION['bAdmin'])
{
	$sSQL = "SELECT usr_per_ID FROM user_usr WHERE usr_per_ID = " . $per_ID;
	if (mysql_num_rows(RunQuery($sSQL)) == 0)
		echo " | <a class=\"SmallText\" href=\"UserEditor.php?NewPersonID=" . $per_ID . "\">" . gettext("Make User") . "</a>" ;
	else
		echo " | <a class=\"SmallText\" href=\"UserEditor.php?PersonID=" . $per_ID . "\">" . gettext("Edit User") . "</a>" ;
}

if ($next_link_text) {
	echo " | $next_link_text";
}
?>

<p class="SmallText">
	<span style="color: red;"><?php echo gettext("Red text"); ?></span> <?php echo gettext("indicates items inherited from the associated family record."); ?>
</p>

<p class="SmallText">
	<?php echo gettext("Entered:"); ?> <?php echo FormatDate($per_DateEntered,true); ?> <?php echo gettext("by"); ?> <?php echo $EnteredFirstName . " " . $EnteredLastName; ?>
<?php

	if (strlen($per_DateLastEdited) > 0)
	{
		?>
			<br>
			<?php echo gettext("Last edited:") . ' ' . FormatDate($per_DateLastEdited,true) . ' ' . gettext("by") . ' ' . $EditedFirstName . " " . $EditedLastName ?>
		</p>
		<?php
	}
	?>

</p>


<?php if ($_SESSION['bNotes']) { ?>
<p>
	<b><?php echo gettext("Notes:"); ?></b>
</p>

<p>
	<a class="SmallText" href="WhyCameEditor.php?PersonID=<?php echo $per_ID ?>"><?php echo gettext("Edit \"Why Came\" Notes"); ?></a></font>
	<br>
	<a class="SmallText" href="NoteEditor.php?PersonID=<?php echo $per_ID ?>"><?php echo gettext("Add a Note to this Record"); ?></a></font>
</p>

<?php

//Loop through all the notes
while($aRow = mysql_fetch_array($rsNotes))
{
	extract($aRow);
	?>

	<p class="ShadedBox")>
		<?php echo $nte_Text ?>
	</p>
	<span class="SmallText"><?php echo gettext("Entered:") . ' ' . FormatDate($nte_DateEntered,True) . ' ' . gettext("by") . ' ' . $EnteredFirstName . " " . $EnteredLastName ?></span>
	<br>
	<?php

	if (strlen($nte_DateLastEdited))
	{ ?>
		<span class="SmallText"><?php echo gettext("Last Edited:") . ' ' . FormatDate($nte_DateLastEdited,True) . ' ' . gettext("by") . ' ' . $EditedFirstName . " " . $EditedLastName ?></span>
		<br>
	<?php
	}
	if ($_SESSION['bNotes']) { ?><a class="SmallText" href="NoteEditor.php?PersonID=<?php echo $iPersonID ?>&NoteID=<?php echo $nte_ID ?>"><?php echo gettext("Edit This Note"); ?></a>&nbsp;|&nbsp;<?php }
	if ($_SESSION['bNotes']) { ?><a class="SmallText" href="NoteDelete.php?NoteID=<?php echo $nte_ID ?>"><?php echo gettext("Delete This Note"); ?></a> <?php }

}
?>
<?php }

require "Include/Footer.php";
?>
