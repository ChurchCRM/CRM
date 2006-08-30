<?php
/*******************************************************************************
 *
 *  filename    : PrintView.php
 *  last change : 2003-01-29
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

// Get this person
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

// Save for later
$sWorkEmail = trim($per_WorkEmail);

// Get the list of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

// Get the actual custom field data
$sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $iPersonID;
$rsCustomData = RunQuery($sSQL);
$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);

// Get the notes for this person
$sSQL = "SELECT nte_Private, nte_ID, nte_Text, nte_DateEntered, nte_EnteredBy, nte_DateLastEdited, nte_EditedBy, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName ";
$sSQL = $sSQL . "FROM note_nte ";
$sSQL = $sSQL . "LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID ";
$sSQL = $sSQL . "LEFT JOIN person_per b ON nte_EditedBy = b.per_ID ";
$sSQL = $sSQL . "WHERE nte_per_ID = " . $iPersonID . " ";
$sSQL = $sSQL . "AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";
$rsNotes = RunQuery($sSQL);

// Get the Groups this Person is assigned to
$sSQL = "SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
		FROM group_grp
		LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
		WHERE person2group2role_p2g2r.p2g2r_per_ID = " . $iPersonID . "
		ORDER BY grp_Name";
$rsAssignedGroups = RunQuery($sSQL);

// Get the Properties assigned to this Person
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'p' AND r2p_record_ID = " . $iPersonID .
		" ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);

// Format the BirthDate
if ($per_BirthMonth > 0 && $per_BirthDay > 0)
{
	$dBirthDate = $per_BirthMonth . "/" . $per_BirthDay;
	if (is_numeric($per_BirthYear))
	{
		$dBirthDate .= "/" . $per_BirthYear;
	}
}
elseif (is_numeric($per_BirthYear))
{
	$dBirthDate = $per_BirthYear;
}
else
{
	$dBirthDate = "";
}

// Assign the values locally, after selecting whether to display the family or person information

SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
$sCity = SelectWhichInfo($per_City, $fam_City, False);
$sState = SelectWhichInfo($per_State, $fam_State, False);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, False);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, False);
$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), False);
$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), False);
$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), False);
$sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, False);

// Set the page title and include HTML header
$sPageTitle = gettext("Printable View");
$iTableSpacerWidth = 10;
require "Include/Header-Short.php";
?>

<table width="200"><tr><td>
<p class="ShadedBox">

<?php
// Print the name and address header
echo "<b><font size=\"4\">" . $per_FirstName . " " . $per_LastName . "</font></b><br>";
echo "<font size=\"3\">";
if ($sAddress1 != "") { echo $sAddress1 . "<br>"; }
if ($sAddress2 != "") { echo $sAddress2 . "<br>"; }
if ($sCity != "") { echo $sCity . ", "; }
if ($sState != "") { echo $sState; }
if ($sZip != "") { echo " " . $sZip; }
if ($sCountry != "") {echo "<br>" . $sCountry; }
echo "</font>";

$iFamilyID = $fam_ID;

if ($fam_ID)
{
	//Get the family members for this family
	$sSQL = "SELECT per_ID, per_Title, per_FirstName, per_LastName, per_Suffix, per_Gender,
		per_BirthMonth, per_BirthDay, per_BirthYear, cls.lst_OptionName AS sClassName,
		fmr.lst_OptionName AS sFamRole
		FROM person_per
		LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
		LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
		WHERE per_fam_ID = " . $iFamilyID . " ORDER BY fmr.lst_OptionSequence";
	$rsFamilyMembers = RunQuery($sSQL);
}
?>

</p></td></tr></table>
<BR>

<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
	<td width="33%" valign="top" align="left">
		<table cellspacing="1" cellpadding="4">
		<tr>
			<td class="LabelColumn"><?php echo gettext("Home Phone:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn"><?php echo $sHomePhone; ?>&nbsp;</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Work Phone:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn"><?php echo $sWorkPhone; ?>&nbsp;</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Mobile Phone:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn"><?php echo $sCellPhone; ?>&nbsp;</td>
		</tr>
		<?php
			$numColumn3Fields = floor($numCustomFields / 3);
			$leftOverFields = $numCustomFields - $numColumn3Fields;
			$numColumn1Fields = ceil($leftOverFields / 2);
			$numColumn2Fields = $leftOverFields - $numColumn1Fields;

			for($i = 1; $i <= $numColumn1Fields; $i++)
			{
				$Row = mysql_fetch_array($rsCustomFields);
				extract($Row);
				$currentData = trim($aCustomData[$custom_Field]);
				if ($type_ID == 11) $custom_Special = $sCountry;
				echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td><td width=\"" . $iTableSpacerWidth . "\"></td>";
				echo "<td class=\"TextColumn\">" . displayCustomField($type_ID, $currentData, $custom_Special) . "</td></tr>";
			}
		?>
		</table>
	</td>

	<td width="33%" valign="top" align="left">
		<table cellspacing="1" cellpadding="4">
		<tr>
			<td class="LabelColumn"><?php echo gettext("Gender:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn">
				<?php
				switch (strtolower($per_Gender))
				{
					case 1:
						echo gettext("Male");
						break;
					case 2:
						echo gettext("Female");
						break;
				} ?>
			</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("BirthDate:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn"><?php echo $dBirthDate; ?>&nbsp;</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Family:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumn">
			<?php if ($fam_Name != "") { echo $fam_Name; } else { echo "Unassigned"; } ?>
			&nbsp;</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Family Role:"); ?></td>
			<td width="<?php echo $iTableSpacerWidth; ?>"></td>
			<td class="TextColumnWithBottomBorder"><?php if ($sFamRole != "") { echo $sFamRole; } else { echo "Unassigned"; } ?>&nbsp;</td>
		</tr>
		<?php
			for($i = 1; $i <= $numColumn2Fields; $i++)
			{
				$Row = mysql_fetch_array($rsCustomFields);
				extract($Row);
				$currentData = trim($aCustomData[$custom_Field]);
				if ($type_ID == 11) $custom_Special = $sCountry;
				echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td><td width=\"" . $iTableSpacerWidth . "\"></td>";
				echo "<td class=\"TextColumn\">" . displayCustomField($type_ID, $currentData, $custom_Special) . "</td></tr>";
			}
		?>
		</table>
	</td>
	<td width="33%" valign="top" align="left">
		<table cellspacing="1" cellpadding="4">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Email:"); ?></td>
				<td width="<?php echo $iTableSpacerWidth; ?>"></td>
				<td class="TextColumnWithBottomBorder"><?php echo $sUnformattedEmail; ?>&nbsp;</td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Work / Other Email:"); ?></td>
				<td width="<?php echo $iTableSpacerWidth; ?>"></td>
				<td class="TextColumnWithBottomBorder"><?php echo $sWorkEmail; ?>&nbsp;</td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Membership Date:"); ?></td>
				<td width="<?php echo $iTableSpacerWidth; ?>"></td>
				<td class="TextColumn"><?php echo FormatDate($per_MembershipDate,false); ?>&nbsp;</td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Classification:"); ?></td>
				<td width="<?php echo $iTableSpacerWidth; ?>"></td>
				<td class="TextColumnWithBottomBorder"><?php echo $sClassName; ?>&nbsp;</td>
			</tr>
		<?php
			for($i = 1; $i <= $numColumn3Fields; $i++)
			{
				$Row = mysql_fetch_array($rsCustomFields);
				extract($Row);
				$currentData = trim($aCustomData[$custom_Field]);
				if ($type_ID == 11) $custom_Special = $sCountry;
				echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td><td width=\"" . $iTableSpacerWidth . "\"></td>";
				echo "<td class=\"TextColumn\">" . displayCustomField($type_ID, $currentData, $custom_Special) . "</td></tr>";
			}
		?>
		</table>
    </td>
</tr>
</table>
<br>

<?php if ($fam_ID) {  ?>

<b><?php echo gettext("Family Members:"); ?></b>
<table cellpadding=5 cellspacing=0 width="100%">
	<tr class="TableHeader">
		<td><?php echo gettext("Name"); ?></td>
		<td><?php echo gettext("Gender"); ?></td>
		<td><?php echo gettext("Role"); ?></td>
		<td><?php echo gettext("Age"); ?></td>
	</tr>
<?php
	$sRowClass = "RowColorA";

	// Loop through all the family members
	while ($aRow = mysql_fetch_array($rsFamilyMembers))
	{
		$per_BirthYear = "";
		$agr_Description = "";

		extract($aRow);

		// Alternate the row style
		$sRowClass = AlternateRowStyle($sRowClass)

		// Display the family member
	?>
		<tr class="<?php echo $sRowClass ?>">
			<td>
				<?php echo $per_FirstName . " " . $per_LastName ?>
				<br>
			</td>
			<td>
				<?php switch ($per_Gender) {case 1: echo gettext("Male"); break; case 2: echo gettext("Female"); break; default: echo "";} ?>&nbsp;
			</td>
			<td>
				<?php echo $sFamRole ?>&nbsp;
			</td>
			<td>
				<?php PrintAge($per_BirthMonth,$per_BirthDay,$per_BirthYear, $per_Flags); ?>
			</td>
		</tr>
	<?php
	}
	echo "</table>";
}
?>
<BR>
<b><?php echo gettext("Assigned Groups:"); ?></b>

<?php

//Initialize row shading
$sRowClass = "RowColorA";

$sAssignedGroups = ",";

//Was anything returned?
if (mysql_num_rows($rsAssignedGroups) == 0)
{
	echo "<p align\"center\">" . gettext("No group assignments.") . "</p>";
}
else
{
	echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
	echo "<tr class=\"TableHeader\">";
	echo "<td width=\"15%\"><b>" . gettext("Group Name") . "</b>";
	echo "<td><b>" . gettext("Role") . "</b></td>";
	echo "</tr>";

	//Loop through the rows
	while ($aRow = mysql_fetch_array($rsAssignedGroups))
	{
		extract($aRow);

		//Alternate the row style
		$sRowClass = AlternateRowStyle($sRowClass);

		// DISPLAY THE ROW
		echo "<tr class=\"" . $sRowClass . "\">";
		echo " <td>" . $grp_Name . "</td>";
		echo " <td>" . $roleName . "</td>";
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
						echo "<tr><td colspan=\"2\"><table width=\"50%\"><tr><td width=\"15%\"></td><td><table width=\"90%\" cellspacing=\"0\">";
						echo "<tr class=\"TinyTableHeader\"><td>Property</td><td>Value</td></tr>";
						$firstRow = false;
					}
					$sRowClass = AlternateRowStyle($sRowClass);
					if ($type_ID == 11) $prop_Special = $sCountry;
					echo "<tr class=\"$sRowClass\"><td>" . $prop_Name . "</td><td>" . displayCustomField($type_ID, $currentData, $prop_Special) . "</td></tr>";
				}
			}
			if (!$firstRow) echo "</table></td></tr></table></td></tr>";
		}

		$sAssignedGroups .= $grp_ID . ",";
	}
	echo "</table>";
}
?>
<BR>
<b><?php echo gettext("Assigned Properties:"); ?></b>

<?php

//Initialize row shading
$sRowClass = "RowColorA";

$sAssignedProperties = ",";

//Was anything returned?
if (mysql_num_rows($rsAssignedProperties) == 0)
{
	echo "<p align\"center\">" . gettext("No property assignments.") . "</p>";
}
else
{
	echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
	echo "<tr class=\"TableHeader\">";
	echo "<td width=\"25%\" valign=\"top\"><b>" . gettext("Name") . "</b>";
	echo "<td valign=\"top\"><b>" . gettext("Value") . "</td>";
	echo "</tr>";

	while ($aRow = mysql_fetch_array($rsAssignedProperties))
	{
		$pro_Prompt = "";
		$r2p_Value = "";
		extract($aRow);

		//Alternate the row style
		$sRowClass = AlternateRowStyle($sRowClass);

		//Display the row
		echo "<tr class=\"" . $sRowClass . "\">";
		echo "<td valign=\"top\">" . $pro_Name . "&nbsp;</td>";
		echo "<td valign=\"top\">" . $r2p_Value . "&nbsp;</td>";

		echo "</tr>";

		$sAssignedProperties .= $pro_ID . ",";
	}
	echo "</table>";
}


if ($_SESSION['bNotes'])
{
	echo "<p><b>" . gettext("Notes:") . "</b></p>";

	// Loop through all the notes
	while($aRow = mysql_fetch_array($rsNotes))
	{
		extract($aRow);
		echo "<p class=\"ShadedBox\")>" . $nte_Text . "</p>";
		echo "<span class=\"SmallText\">" . gettext("Entered:") . FormatDate($nte_DateEntered,True) . "</span><br>";

		if (strlen($nte_DateLastEdited))
		{
			echo "<span class=\"SmallText\">" . gettext("Last Edited:") . FormatDate($nte_DateLastEdited,True) . ' ' . gettext("by") . ' ' . $EditedFirstName . " " . $EditedLastName . "</span><br>";
		}
	}
}

require "Include/Footer-Short.php";
?>
