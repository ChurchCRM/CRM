<?php
/*******************************************************************************
 *
 *  filename    : GroupPropsEditor.php
 *  last change : 2003-02-09
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for the special properties of a group member
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// Security: user must be allowed to edit records to use this page.
if (!$_SESSION['bEditRecords'])
{
	Redirect("Menu.php");
	exit;
}

$sPageTitle = gettext("Group Member Properties Editor");

// Get the Group and Person IDs from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');
$iPersonID = FilterInput($_GET["PersonID"],'int');

// Get some info about this person.  per_Country is needed in case there are phone numbers.
$sSQL = "SELECT per_FirstName, per_LastName, per_Country, per_fam_ID FROM person_per WHERE per_ID = " . $iPersonID;
$rsPersonInfo = RunQuery($sSQL);
extract(mysql_fetch_array($rsPersonInfo));

if ($per_fam_ID > 0)
{
	$sSQL = "SELECT fam_Country FROM family_fam WHERE fam_ID = " . $per_fam_ID;
	$rsFam = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFam));
}

$sPhoneCountry = SelectWhichInfo($per_Country,$fam_Country,false);

// Get the name of this group.
$sSQL = "SELECT grp_Name FROM group_grp WHERE grp_ID = " . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysql_fetch_array($rsGroupInfo));

// We assume that the group selected has a special properties table and that it is populated
//  with values for each group member.

// Get the properties list for this group: names, descriptions, types and prop_ID for ordering;  will process later..

$sSQL = "SELECT groupprop_master.* FROM groupprop_master
			WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
$rsPropList = RunQuery($sSQL);

// Is this the second pass?
if (isset($_POST["GroupPropSubmit"]))
{
	// Process all HTTP post data based upon the list of properties data we are expecting
	// If there is an error message, it gets assigned to an array of strings, $aPropErrors, for use in the form.

	$bErrorFlag = false;

	while ( $rowPropList = mysql_fetch_array($rsPropList, MYSQL_BOTH) )
	{
		extract($rowPropList);

		$currentFieldData = FilterInput($_POST[$prop_Field]);

		$bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $prop_Field, $aPropErrors);

		// assign processed value locally to $aPersonProps so we can use it to generate the form later
		$aPersonProps[$prop_Field] = $currentFieldData;
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		mysql_data_seek($rsPropList,0);

		$sSQL = "UPDATE groupprop_" . $iGroupID . " SET ";

		while ( $rowPropList = mysql_fetch_array($rsPropList, MYSQL_BOTH) )
		{
			extract($rowPropList);
			$currentFieldData = trim($aPersonProps[$prop_Field]);

			sqlCustomField($sSQL, $type_ID, $currentFieldData, $prop_Field, $sPhoneCountry);
		}

		// chop off the last 2 characters (comma and space) added in the last while loop iteration.
		$sSQL = substr($sSQL,0,-2);

		$sSQL .= " WHERE per_ID = " . $iPersonID;

		//Execute the SQL
		RunQuery($sSQL);

		// Return to the Person View
		Redirect("PersonView.php?PersonID=" . $iPersonID);
	}
}
else
{
	// First Pass
	// we are always editing, because the record for a group member was created when they were added to the group

	// Get the existing data for this group member
	$sSQL = "SELECT * FROM groupprop_" . $iGroupID . " WHERE per_ID = " . $iPersonID;
	$rsPersonProps = RunQuery($sSQL);
	$aPersonProps = mysql_fetch_array($rsPersonProps, MYSQL_BOTH);
}

require "Include/Header.php";

if (mysql_num_rows($rsPropList) == 0)
{
?>
<form>
<h3><?php echo gettext("This group currently has no properties!  You can add them in the Group Editor."); ?></h3>
<BR>
<input type="button" class="icButton" <?php echo 'value="' . gettext("Return to Person Record") . '"'; ?> Name="Cancel" onclick="javascript:document.location='PersonView.php?PersonID=<?php echo $iPersonID; ?>';">
</form>
<?php
} else {

echo "<h2>" . gettext("Editing") . " <i>" . $grp_Name . "</i> " . gettext("data for member") . " <i>" . $per_FirstName . " " . $per_LastName . "</i></h2><BR>";

// Construct the form

?>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . "?PersonID=" . $iPersonID . "&GroupID=" . $iGroupID; ?>" name="GroupPropEditor">

	<table cellpadding="3">
		<tr>
			<td align="center" colspan="3">
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="GroupPropSubmit">
			&nbsp;
			<input type="button" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> Name="Cancel" onclick="javascript:document.location='PersonView.php?PersonID=<?php echo $iPersonID; ?>';">
			<br><br>
			</td>
		</tr>

	<?php

	// Make sure we're at the beginning of the properties list resource (2nd pass code used it)
	mysql_data_seek($rsPropList,0);

	while ( $rowPropList = mysql_fetch_array($rsPropList, MYSQL_BOTH) )
	{
		extract($rowPropList);

		?>
		<tr>
			<td class="LabelColumn"><?php echo $prop_Name; ?></td>
			<td class="TextColumn">

			<?php

			$currentFieldData = trim($aPersonProps[$prop_Field]);

			if ($type_ID == 11) $prop_Special = $sPhoneCountry;	// ugh.. an argument with special cases!

			formCustomField($type_ID, $prop_Field, $currentFieldData, $prop_Special, !isset($_POST["GroupPropSubmit"]));

			echo "<span style=\"color: red; \">" . $aPropErrors[$prop_Field] . "</span>";
			?></td>

			<td class="TextColumn"><?php echo $prop_Description; ?></td>
		</tr>
	<?php } ?>

		<tr>
			<td align="center" colspan="3">
			<br><br>
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="GroupPropSubmit">
			&nbsp;
			<input type="button" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> Name="Cancel" onclick="javascript:document.location='PersonView.php?PersonID=<?php echo $iPersonID; ?>';">
			</td>
		</tr>
	</table>
	</form>

<?php
}

require "Include/Footer.php";
?>
