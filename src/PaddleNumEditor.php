<?php
/*******************************************************************************
 *
 *  filename    : PaddleNumEditor.php
 *  last change : 2009-04-15
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2009 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$iPaddleNumID = FilterInputArr($_GET,"PaddleNumID",'int');
$linkBack = FilterInputArr($_GET,"linkBack");

if ($iPaddleNumID > 0) {
	$sSQL = "SELECT * FROM paddlenum_pn WHERE pn_ID = '$iPaddleNumID'";
	$rsPaddleNum = RunQuery($sSQL);
	$thePaddleNum = mysql_fetch_array($rsPaddleNum);
	$iCurrentFundraiser = $thePaddleNum["pn_fr_ID"];
} else {
	$iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];
}

// Get the current fundraiser data
if ($iCurrentFundraiser) {
	$sSQL = "SELECT * from fundraiser_fr WHERE fr_ID = " . $iCurrentFundraiser;
	$rsDeposit = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDeposit));
}

// SQL to get multibuy items 
$sMultibuyItemsSQL = "SELECT di_ID, di_title FROM donateditem_di WHERE di_multibuy='1' AND di_FR_ID=" . $iCurrentFundraiser;

//Set the page title
$sPageTitle = gettext("Buyer Number Editor");

//Is this the second pass?
if (isset($_POST["PaddleNumSubmit"]) || isset($_POST["PaddleNumSubmitAndAdd"]) || isset($_POST["GenerateStatement"]))
{
	//Get all the variables from the request object and assign them locally
	$iNum = FilterInput ($_POST["Num"]);
	$iPerID = FilterInput ($_POST["PerID"]);

	$rsMBItems = RunQuery($sMultibuyItemsSQL); // Go through the multibuy items, see if this person bought any
	while ($aRow = mysql_fetch_array($rsMBItems)) {
		extract($aRow);
		$mbName = "MBItem" . $di_ID;
		$iMBCount = FilterInput ($_POST[$mbName], 'int');
		if ($iMBCount > 0) { // count for this item is positive.  If a multibuy record exists, update it.  If not, create it.
			$sqlNumBought = "SELECT mb_count from multibuy_mb WHERE mb_per_ID=".$iPerID." AND mb_item_ID=".$di_ID;
			$rsNumBought = RunQuery($sqlNumBought);
			$numBoughtRow = mysql_fetch_array($rsNumBought);
			if ($numBoughtRow) {
				$sSQL = "UPDATE multibuy_mb SET mb_count=".$iMBCount." WHERE mb_per_ID=".$iPerID." AND mb_item_ID=".$di_ID;
				RunQuery($sSQL);
			} else {
				$sSQL = "INSERT INTO multibuy_mb (mb_per_ID, mb_item_ID, mb_count) VALUES (".$iPerID.",".$di_ID.",".$iMBCount.")";
				RunQuery($sSQL);
			}
		} else { // count is zero, if it was positive before there is a multibuy record that needs to be deleted
			$sSQL = "DELETE FROM multibuy_mb WHERE mb_per_ID=".$iPerID." AND mb_item_ID=".$di_ID;
			RunQuery($sSQL);
		}
	}
	
	// New PaddleNum
	if (strlen($iPaddleNumID) < 1)
	{
		$sSQL = "INSERT INTO paddlenum_pn (pn_fr_ID, pn_Num, pn_per_ID)
		         VALUES (" . $iCurrentFundraiser . ",'" . $iNum . "','" . $iPerID . "')";
		$bGetKeyBack = True;
	// Existing record (update)
	} else {
		$sSQL = "UPDATE paddlenum_pn SET pn_fr_ID = " . $iCurrentFundraiser . ", pn_Num = '". $iNum . "', pn_per_ID = '" . $iPerID . "'";
		$sSQL .= " WHERE pn_ID = " . $iPaddleNumID;
		$bGetKeyBack = false;
	}

	//Execute the SQL
	RunQuery($sSQL);

	// If this is a new PaddleNum or deposit, get the key back
	if ($bGetKeyBack) {
		$sSQL = "SELECT MAX(pn_ID) AS iPaddleNumID FROM paddlenum_pn";
		$rsPaddleNumID = RunQuery($sSQL);
		extract(mysql_fetch_array($rsPaddleNumID));
	}

	if (isset($_POST["PaddleNumSubmit"])) {
		Redirect("PaddleNumEditor.php?PaddleNumID=" . $iPaddleNumID . "&linkBack=" . $linkBack);
	} else if (isset($_POST["PaddleNumSubmitAndAdd"])) {
		//Reload to editor to add another record
		Redirect("PaddleNumEditor.php?CurrentFundraiser=$iCurrentFundraiser&linkBack=", $linkBack);
	} else if (isset($_POST["GenerateStatement"])) {
		//Jump straight to generating the statement report
		Redirect("Reports/FundRaiserStatement.php?PaddleNumID=$iPaddleNumID");
	}
	
} else {

	//FirstPass
	//Are we editing or adding?
	if (strlen($iPaddleNumID) > 0) {
		//Editing....
		//Get all the data on this record
		$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
	                       a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
	         FROM paddlenum_pn
	         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
	         WHERE pn_ID = '" . $iPaddleNumID . "'"; 
		$rsPaddleNum = RunQuery($sSQL);
		extract(mysql_fetch_array($rsPaddleNum));

		$iNum = $pn_Num;
		$iPerID = $pn_per_ID;
	} else {
		//Adding....
		//Set defaults
		$sSQL = "SELECT COUNT(*) AS topNum FROM paddlenum_pn WHERE pn_fr_ID=" . $iCurrentFundraiser;
		$rsGetMaxNum = RunQuery($sSQL);
		extract(mysql_fetch_array($rsGetMaxNum));

		$iNum = $topNum+1;
		$iPerID = 0;
	}
}

//Get People for the drop-down
$sPeopleSQL = "SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName";

require "Include/Header.php";

?>
<div class="box box-body">
<form method="post" action="PaddleNumEditor.php?<?= "CurrentFundraiser=" . $iCurrentFundraiser . "&PaddleNumID=" . $iPaddleNumID . "&linkBack=" . $linkBack ?>" name="PaddleNumEditor">

<table cellpadding="3" align="center">
	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext("Save") ?>" name="PaddleNumSubmit">
			<input type="submit" class="btn" value="<?= gettext("Generate Statement") ?>" name="GenerateStatement">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"btn\" value=\"" . gettext("Save and Add") . "\" name=\"PaddleNumSubmitAndAdd\">\n"; } ?>
			<input type="button" class="btn" value="<?= gettext("Back") ?>" name="PaddleNumCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table border="0" width="100%" cellspacing="0" cellpadding="4">
			<tr>
			<td width="50%" valign="top" align="left">
			<table cellpadding="3">
				<tr>
					<td class="LabelColumn"><?= gettext("Number:") ?></td>
					<td class="TextColumn"><input type="text" name="Num" id="Num" value="<?= $iNum ?>"></td>
				</tr>
				
				<tr>
					<td class="LabelColumn"><?= gettext("Buyer:") ?>
					</td>
					<td class="TextColumn">
						<select name="PerID">
							<option value="0" selected><?= gettext("Unassigned") ?></option>
							<?php
							$rsPeople = RunQuery($sPeopleSQL);
							while ($aRow = mysql_fetch_array($rsPeople))
							{
								extract($aRow);
								echo "<option value=\"" . $per_ID . "\"";
								if ($iPerID == $per_ID) { echo " selected"; }
								echo ">" . $per_LastName . ", " . $per_FirstName;
								echo " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
							}
							?>
	
						</select>
					</td>
				</tr>
			</table>
			</td>
		
			<td width="50%" valign="top" align="center">
			<table cellpadding="3">
					<?php
					$rsMBItems = RunQuery($sMultibuyItemsSQL);
					while ($aRow = mysql_fetch_array($rsMBItems))
					{
						extract($aRow);
						
						$sqlNumBought = "SELECT mb_count from multibuy_mb WHERE mb_per_ID=".$iPerID." AND mb_item_ID=".$di_ID;
						$rsNumBought = RunQuery($sqlNumBought);
						$numBoughtRow = mysql_fetch_array($rsNumBought);
						if ($numBoughtRow) {
							extract($numBoughtRow);
						} else {
							$mb_count = 0;
						}
					?>
						<tr>
							<td class="LabelColumn"><?= $di_title ?></td>
							<td class="TextColumn"><input type="text" name="MBItem<?= $di_ID ?>" id="MBItem<?= $di_ID ?>" value="<?= $mb_count ?>"></td>
						</tr>
					<?php
					}
					?>
				
			</table>
			</td>
			</tr>
			
			</table>
			</tr>
	</table>

</form>
</div>
<?php require "Include/Footer.php" ?>
