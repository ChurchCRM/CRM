<?php
/*******************************************************************************
 *
 *  filename    : DonationFundEditor.php
 *  last change : 2003-03-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for donation funds
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// Security: user must be administrator to use this page
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

if (isset($_GET["Action"])) {
	$sAction = $_GET["Action"];
} else {
	$sAction = '';
}
if (isset ($_GET["Fund"])) {
	$sFund = FilterInput($_GET["Fund"],'int');
} else {
	$sFund = '';
}

$sDeleteError = "";
$bErrorFlag = false;
$aNameErrors = array ();
$bNewNameError = false;

if ($sAction = 'delete' && strlen($sFund) > 0)
{
	$sSQL = "DELETE FROM donationfund_fun WHERE fun_ID = '" . $sFund . "'";
	RunQuery($sSQL);
}

$sPageTitle = gettext("Donation Fund Editor");

require "Include/Header.php"; ?>


<div class="box box-body">


<?php

// Does the user want to save changes to text fields?
if (isset($_POST["SaveChanges"]))
{
	$sSQL = "SELECT * FROM donationfund_fun";
	$rsFunds = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsFunds);

	for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++ )
	{
		$aNameFields[$iFieldID] = FilterInput($_POST[$iFieldID . "name"]);

		if ( strlen($aNameFields[$iFieldID]) == 0 )
		{
			$aNameErrors[$iFieldID] = true;
			$bErrorFlag = true;
		}
		else
		{
			$aNameErrors[$iFieldID] = false;
		}

		$aDescFields[$iFieldID] = FilterInput($_POST[$iFieldID . "desc"]);
		$aActiveFields[$iFieldID] = $_POST[$iFieldID . "active"];

		$aRow = mysql_fetch_array($rsFunds);
		$aIDFields[$iFieldID] = $aRow[0];
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		for( $iFieldID=1; $iFieldID <= $numRows; $iFieldID++ )
		{
			if ($aActiveFields[$iFieldID] == 1)
				$temp = 'true';
			else
				$temp = 'false';

			$sSQL = "UPDATE donationfund_fun
					SET `fun_Name` = '" . $aNameFields[$iFieldID] . "',
						`fun_Description` = '" . $aDescFields[$iFieldID] . "',
						`fun_Active` = '" . $temp . "' " .
					"WHERE `fun_ID` = '" . $aIDFields[$iFieldID] . "';";

			RunQuery($sSQL);
		}
	}
}

else
{
	// Check if we're adding a fund
	if (isset($_POST["AddField"]))
	{
		$newFieldName = FilterInput($_POST["newFieldName"]);
		$newFieldDesc = FilterInput($_POST["newFieldDesc"]);

		if (strlen($newFieldName) == 0)
		{
			$bNewNameError = true;
		}
		else
		{
			// Insert into the funds table
			$sSQL = "INSERT INTO `donationfund_fun`
					(`fun_ID` , `fun_Name` , `fun_Description`)
					VALUES (0, '" . $newFieldName . "', '" . $newFieldDesc . "');";
			RunQuery($sSQL);

			$bNewNameError = false;
		}
	}

	// Get data for the form as it now exists..
	$sSQL = "SELECT * FROM donationfund_fun";

	$rsFunds = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsFunds);

	// Create arrays of the fundss.
	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsFunds, MYSQL_BOTH);
		extract($aRow);

		$aIDFields[$row] = $fun_ID;
		$aNameFields[$row] = $fun_Name;
		$aDescFields[$row] = $fun_Description;
		$aActiveFields[$row] = ($fun_Active == 'true');
		$aNameErrors[$row] = false;
	}
}

// Construct the form
?>

<script language="javascript">

function confirmDeleteFund( Fund ) {
var answer = confirm (<?= '"' . gettext("Are you sure you want to delete this fund?") . '"' ?>)
if ( answer )
	window.location="DonationFundEditor.php?Fund=" + Fund + "&Action=delete"
}
</script>

<form method="post" action="DonationFundEditor.php" name="FundsEditor">

<div class="alert alert-warning">
		<i class="fa fa-ban"></i>
		<?= gettext("Warning: Field changes will be lost if you do not 'Save Changes' before using a delete or 'add new' button!") ?>

</div>

<?php
		if ( $bErrorFlag ) echo gettext("Invalid fields or selections. Changes not saved! Please correct and try again!");
		if (strlen($sDeleteError) > 0) echo $sDeleteError;
		?>

<table class="table">

<?php
if ($numRows == 0)
{
?>
	<center><h2><?= gettext("No funds have been added yet") ?></h2>
	</center>
<?php
}
else
{
?>
		<tr>
			<th><?= gettext("Name") ?></th>
			<th><?= gettext("Description") ?></th>
			<th><?= gettext("Active") ?></th>
            <th><?= gettext("Delete") ?></th>
		</tr>

	<?php

	for ($row=1; $row <= $numRows; $row++)
	{
		?>
		<tr>


			<td class="TextColumn" align="center">
				<input type="text" name="<?= $row . "name" ?>" value="<?= htmlentities(stripslashes($aNameFields[$row]),ENT_NOQUOTES, "UTF-8") ?>" size="20" maxlength="30">
				<?php
				if ($aNameErrors[$row])
					echo "<span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . " </span>";
				?>
			</td>

			<td class="TextColumn">
				<input type="text" Name="<?php echo $row . "desc" ?>" value="<?= htmlentities(stripslashes($aDescFields[$row]),ENT_NOQUOTES, "UTF-8") ?>" size="40" maxlength="100">
			</td>
			<td class="TextColumn" align="center" nowrap>
				<input type="radio" Name="<?= $row ?>active" value="1" <?php if ($aActiveFields[$row]) echo " checked" ?>><?= gettext("Yes") ?>
				<input type="radio" Name="<?= $row ?>active" value="0" <?php if (!$aActiveFields[$row]) echo " checked" ?>><?= gettext("No") ?>
			</td>
            <td class="TextColumn" width="5%">
				<input type="button" class="btn btn-danger" value="<?= gettext("delete") ?>" Name="delete" onclick="confirmDeleteFund('<?= $aIDFields[$row] ?>');" >
			</td>

		</tr>
	<?php } ?>

		<tr>
			<td colspan="5">
			<table width="100%">
				<tr>
					<td width="30%"></td>
					<td width="40%" align="center" valign="bottom">
						<input type="submit" class="btn btn-primary" value="<?= gettext("Save Changes") ?>" Name="SaveChanges">
					</td>
					<td width="30%"></td>
				</tr>
			</table>
			</td>
			<td>
		</tr>
<?php } ?>
		<tr><td colspan="5"><hr></td></tr>
		<tr>
			<td colspan="5">
			<table width="100%">
				<tr>
					<td width="15%"></td>
					<td valign="top">
						<div><?= gettext("Name:") ?></div>
						<input type="text" name="newFieldName" size="30" maxlength="30">
						<?php if ( $bNewNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . "</span></div>"; ?>
						&nbsp;
					</td>
					<td valign="top">
						<div><?= gettext("Description:") ?></div>
						<input type="text" name="newFieldDesc" size="40" maxlength="100">
						&nbsp;
					</td>
					<td>
						<input type="submit" class="btn btn-primary" value="<?= gettext("Add New Fund") ?>" Name="AddField">
					</td>
					<td width="15%"></td>
				</tr>
			</table>
			</td>
		</tr>

	</table>
	</form>
</div>

<?php require "Include/Footer.php" ?>
