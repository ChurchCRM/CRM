<?php
/*******************************************************************************
 *
 *  filename    : GroupReports.php
 *  last change : 2003-09-03
 *  description : Detailed reports on group members
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Federico Nebiolo, Chris Gebhardt
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

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Group reports");
require "Include/Header.php";

?>

<script type="text/javascript">
var IFrameObj; // our IFrame object

function UpdateRoles()
{
	var group_ID = document.getElementById('GroupID').value;
	if (!document.createElement) {return true};
	var IFrameDoc;
	var URL = 'RPCdummy.php?mode=GroupRolesSelect&data=' + group_ID;
	if (!IFrameObj && document.createElement) {
		var tempIFrame=document.createElement('iframe');
		tempIFrame.setAttribute('id','RSIFrame');
		tempIFrame.style.border='0px';
		tempIFrame.style.width='0px';
		tempIFrame.style.height='0px';
		IFrameObj = document.body.appendChild(tempIFrame);

		if (document.frames) {
			// For IE5 Mac
			IFrameObj = document.frames['RSIFrame'];
		}
	}

	if (navigator.userAgent.indexOf('Gecko') !=-1
		&& !IFrameObj.contentDocument) {
		// For NS6
		setTimeout('AddToCart()',10);
		return false;
	}

	if (IFrameObj.contentDocument) {
		// For NS6
		IFrameDoc = IFrameObj.contentDocument;
	} else if (IFrameObj.contentWindow) {
		// For IE5.5 and IE6
		IFrameDoc = IFrameObj.contentWindow.document;
	} else if (IFrameObj.document) {
		// For IE5
		IFrameDoc = IFrameObj.document;
	} else {
		return true;
	}

	IFrameDoc.location.replace(URL);
	return false;
}

function updateGroupRoles(generated_html)
{
	if (generated_html == "invalid") {
		document.getElementById('GroupRole').innerHTML = <?php echo '\'<p class="LargeError">' . gettext("Invalid Group or No Roles Available!") . '<p>\';'; ?>
	} else {
		document.getElementById('GroupRole').innerHTML = generated_html;
	}
}
</script>

<?php if (!isset($_POST["GroupID"])) { ?>

<p align="center"><?php echo gettext("Select the group you would like to report:"); ?></p>
<form method="POST" action="GroupReports.php">
<table align="center">
	<tr>
		<td class="LabelColumn"><?php echo gettext("Select Group:"); ?></td>
		<td class="TextColumn">
			<?php
			// Create the group select drop-down
			echo "<select id=\"GroupID\" name=\"GroupID\" onChange=\"UpdateRoles();\"><option value=\"0\">". gettext('None') . "</option>";
			while ($aRow = mysql_fetch_array($rsGroups)) {
				extract($aRow);
				echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
			}
			echo "</select>";
			?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Select Role:"); ?></td>
		<td class="TextColumn"><span id="GroupRole"><?php echo gettext("No Group Selected"); ?></span></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Only cart persons?:"); ?></td>
		<td class="TextColumn"><input type="checkbox" Name="OnlyCart" value="1"></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Report Model:"); ?></td>
		<td class="TextColumn">
			<input type="radio" Name="ReportModel" value="1" checked><?php echo gettext("Report for group and role selected"); ?><br>
			<input type="radio" Name="ReportModel" value="2"><?php echo gettext("Report for any role in group selected"); ?><br>
<?php //			<input type="radio" Name="ReportModel" value="3"><?php echo gettext("Report any group and role"); ?>
		</td>
	</tr>
</table>
<p align="center">
<BR>
<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Next") . '"'; ?>>
<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='ReportList.php';">
</p>
</form>

<?php } else {

$iGroupID = FilterInput($_POST['GroupID'],'int');

?>

<form method="POST" action="Reports/GroupReport.php">
<input type="hidden" Name="GroupID" value="<?php echo $iGroupID;?>">
<input type="hidden" Name="GroupRole" value="<?php echo $_POST['GroupRole'];?>">
<input type="hidden" Name="OnlyCart" value="<?php echo $_POST['OnlyCart'];?>">
<input type="hidden" Name="ReportModel" value="<?php echo $_POST['ReportModel'];?>">

<?php

$sSQL = "SELECT prop_Field, prop_Name FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
$rsPropFields = RunQuery($sSQL);

?>

<p align="center" class="MediumText"><?php echo gettext("Select which information you want to include"); ?></p>

<table align="center">
	<tr>
		<td class="LabelColumn"><?php echo gettext("Standard Info:"); ?></td>
		<td class="TextColumn">
			<input type="checkbox" Name="AddressEnable" value="1"> <?php echo gettext("Address");?> <br>
			<input type="checkbox" Name="HomePhoneEnable" value="1"> <?php echo gettext("Home Phone");?> <br>
			<input type="checkbox" Name="WorkPhoneEnable" value="1"> <?php echo gettext("Work Phone");?> <br>
			<input type="checkbox" Name="CellPhoneEnable" value="1"> <?php echo gettext("Cell Phone");?> <br>
			<input type="checkbox" Name="EmailEnable" value="1"> <?php echo gettext("Email");?> <br>
			<input type="checkbox" Name="OtherEmailEnable" value="1"> <?php echo gettext("Other Email");?> <br>
			<input type="checkbox" Name="GroupRoleEnable" value="1"> <?php echo gettext("GroupRole");?> <br>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Group-Specific Property Fields:"); ?></td>
		<td class="TextColumn">
			<?php
				if (mysql_num_rows($rsPropFields) > 0)
				{
					while ($aRow = mysql_fetch_array($rsPropFields)) {
						extract($aRow);
						echo "<input type=\"checkbox\" Name=\"" . $prop_Field . "enable\" value=\"1\">" . $prop_Name . "<br>";
					}
				}
				else
					echo gettext("None");
			?>
		</td>
	</tr>
</table>

<p align="center">
<BR>
<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Create Report") . '"'; ?>>
<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';">
</p>
</form>

<?php } ?>


<?php
require "Include/Footer.php";
?>
