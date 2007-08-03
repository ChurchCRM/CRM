<?php
/*******************************************************************************
 *
 *  filename    : OptionsManager.php
 *  last change : 2003-04-16
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  OptionName : Interface for editing simple selection options such as those
 *              : used for Family Roles, Classifications, and Group Types
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

// Security: User must have Manage Groups permission
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Select the proper settings for the editor mode
$adj = gettext("Menu Tree");
$noun = gettext("Definition");
$menu = FilterInput($_GET["menu"],'string');
$embedded = true;
$bErrorFlag = false;
$bNewNameError = false;

$sSQL = "SELECT '' FROM menuconfig_mcf WHERE name = '$menu' AND ismenu = 1 ORDER BY sortorder";
$rsTemp = RunQuery($sSQL);

// Validate that this is a valid menu item
if (mysql_num_rows($rsTemp) == 0) {
	Redirect("Menu.php");
}

// Check if we're adding a field
if (isset($_POST["AddField"]))
{
	$newFieldName = FilterInput($_POST["newFieldName"]);

	if (strlen($newFieldName) == 0)
	{
		$bNewNameError = 1;
		$bNewNameError = true;
		$bErrorFlag = true;

	}
	else
	{
		// Check for a duplicate option name
		$sSQL = "SELECT '' FROM menuconfig_mcf WHERE parent = '$menu' AND name = '" . $newFieldName . "'";
		$rsCount = RunQuery($sSQL);
		if (mysql_num_rows($rsCount) > 0)
		{
			$bNewNameError = 2;
			$bNewNameError = true;
			$bErrorFlag = true;
		}
		else
		{
			// Get count of the menu item
			$sSQL = "SELECT '' FROM menuconfig_mcf WHERE parent = '$menu'";
			$rsTemp = RunQuery($sSQL);
			$numRows = mysql_num_rows($rsTemp);
			$newOptionSequence = $numRows + 1;

			// Insert into the appropriate options table
			$sSQL = "INSERT INTO menuconfig_mcf ( mid , name , parent , ismenu , content , uri , statustext , security_grp , session_var , session_var_in_text , session_var_in_uri , url_parm_name , active , sortorder )
					VALUES (NULL , '$newFieldName', '$menu', '$ismenu', '$content', '$uri', '$statustext', '$security_grp', NULL , FALSE, FALSE, NULL , FALSE, '$newOptionSequence')";
			RunQuery($sSQL);
		}
	}
}

if (isset($_POST["SaveChanges"]))
{
	$bErrorFlag = false;
	$bDuplicateFound = false;

	// Get the original list of options..
	$sSQL = "SELECT mid, name FROM menuconfig_mcf WHERE parent='$menu' ORDER BY sortorder";
	$rsList = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsList);

	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsList, MYSQL_BOTH);
		$aOldNameFields[$row] = $aRow["name"];

		$aNameFields[$row] = FilterInput($_POST[$row . "name"]);
		$amid[$row] = FilterInput($_POST[$row."mid"]);
	}

	for ($row = 1; $row <= $numRows; $row++)
	{
		if ( strlen($aNameFields[$row]) == 0 )
		{
			$aNameError[$row] = 1;
			$bErrorFlag = true;
		}
		elseif ($row < $numRows)
		{
			$aNameErrors[$row] = 0;
			for ($rowcmp = $row + 1; $rowcmp <= $numRows; $rowcmp++)
			{
				if ($aNameFields[$row] == $aNameFields[$rowcmp])
				{
					$bDuplicateNameError[$row] = gettext("Name cannot be duplicated");
					$aNameError[$row] = 2;
					$bErrorFlag = true;
					break;
				}
			}
		}
		else
		{
			$aNameErrors[$row] = 0;
		}
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		for( $row=1; $row <= $numRows; $row++ )
		{
			// Update the type's name if it has changed from what was previously stored
			if ($aOldNameFields[$row] != $aNameFields[$row])
			{
				$sSQL = "UPDATE menuconfig_mcf SET `name` = '" . $aNameFields[$row] . "' WHERE `mid` = '$mid'";
				RunQuery($sSQL);
			}
		}
	}
}

// Get data for the form as it now exists..

$sSQL = "SELECT mid, name, ismenu FROM menuconfig_mcf WHERE parent = '$menu' ORDER BY sortorder";

$rsRows = RunQuery($sSQL);
$numRows = mysql_num_rows($rsRows);

// Create arrays of the option names and IDs
for ($row = 1; $row <= $numRows; $row++)
{
	$aRow = mysql_fetch_array($rsRows, MYSQL_BOTH);

	if (!$bErrorFlag)
	{
		$aNameFields[$row] = $aRow["name"];
		$aIDs[$row] = $aRow["mid"];
		$aIsMenu[$row] = $aRow["ismenu"];
	}
}

//Set the starting row color
$sRowClass = "RowColorA";

// Use a minimal page header if this form is going to be used within a frame
if ($embedded)
	include "Include/Header-Minimal.php";
else
{
	$sPageTitle = $adj . ' ' . $noun . "s Editor:";
	include "Include/Header.php";
}

?>
<script language="JavaScript" type="text/javascript">
  <!--
	function closeself() {
		opener.location.reload(true);
		self.close();
	}
  // -->
</script>

<form method="post" action="MenuManager.php?<?php echo "menu=$menu" ?>" name="MenuManager">

<center><br>

<?php

if ( $bErrorFlag )
{
	echo "<span class=\"MediumLargeText\" style=\"color: red;\">";
	if ($bDuplicateFound) echo "<br>" . gettext("Error: Duplicate") . " " . $adj . " " . $noun . gettext("s are not allowed.");
	echo "<br>" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span><br><br>";
}
?>

<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">


<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:closeself();">

</center>
<br>
<table cellpadding="3" width="30%" align="center">

<?php
for ($row=1; $row <= $numRows; $row++)
{
	?>
	<tr align="center">
		<td class="LabelColumn">
			<b>
			<?php
				echo $row;
			?>
			</b>
		</td>

		<td class="TextColumn" nowrap>

			<?php
			if ($row != 1)
				echo "<a href=\"MenuManagerRowOps.php?menu=$menu&amp;Order=$row&amp;ID=" . $aIDs[$row] . "&Action=up\"><img src=\"Images/uparrow.gif\" border=\"0\"></a>";
			if ($row < $numRows)
				echo "<a href=\"MenuManagerRowOps.php?menu=$menu&amp;Order=$row&amp;ID=" . $aIDs[$row] . "&Action=down\"><img src=\"Images/downarrow.gif\" border=\"0\"></a>";
			if ($numRows > 1)
				echo "<a href=\"MenuManagerRowOps.php?menu=$menu&amp;Order=$row&amp;ID=" . $aIDs[$row] . "&Action=delete\"><img src=\"Images/x.gif\" border=\"0\"></a>";
			?>
		</td>
		<td class="TextColumn">
			<span class="SmallText">
				<input type="text" name="<?php echo $row . "name"; ?>" value="<?php echo htmlentities(stripslashes($aNameFields[$row]),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="40">
				<input type="hidden" name="<?php echo $row . "mid"; ?>" value="<?php echo $aIDs[$row]; ?>">
				<?php 
					if ($aNameErrors[$row] == 1) {
						echo "<div><font color=\"red\">".gettext("Name cannot be empty")."</font></div>";
					} else {
						if ($aNameErrors[$row] ==2) {
							echo "<div><font color=\"red\">".gettext("Duplicate name")."</font></div>";
						}
					}
				?>
				<div><font color="red"><?php echo $aNameEmptyError[$row]; ?></font></div>
				<div><font color="red"><?php echo $aDuplicateNameError[$row]; ?></font></div>
			</span>

		</td>

	</tr>
<?php } ?>

</table>

<br>
<center>
New <?php echo $noun . " " . gettext("Name:"); ?>&nbsp;
<span class="SmallText">
	<input type="text" name="newFieldName" size="30" maxlength="40">
</span>
&nbsp;
<input type="submit" class="icTinyButton" <?php echo 'value="' . gettext("Add New") . ' ' . $adj . ' ' . $noun . '"'?> Name="AddField">
<?php
	if ($bNewNameError > 0)
	{
		echo "<div><span style=\"color: red;\"><BR>";
		if ( $bNewNameError == 1 )
			echo "<div><font color=\"red\">".gettext("Name cannot be empty")."</font></div>";
		else
			echo "<div><font color=\"red\">".gettext("Duplicate name")."</font></div>";
		echo "</span></div>";
	}
?>
</center>
</form>

<?php
if ($embedded)
	echo "</body></html>";
else
	include "Include/Footer.php";
?>