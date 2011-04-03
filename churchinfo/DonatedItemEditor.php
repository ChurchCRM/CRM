<?php
/*******************************************************************************
 *
 *  filename    : DonatedItemEditor.php
 *  last change : 2009-04-15
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2009 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$iDonatedItemID = FilterInput($_GET["DonatedItemID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);
$iCurrentFundraiser = FilterInput($_GET["CurrentFundraiser"]);

if ($iDonatedItemID > 0) {
	$sSQL = "SELECT * FROM donateditem_di WHERE di_ID = '$iDonatedItemID'";
	$rsDonatedItem = RunQuery($sSQL);
	$theDonatedItem = mysql_fetch_array($rsDonatedItem);
	$iCurrentFundraiser = $theDonatedItem["plg_depID"];
	$DonatedItemOrPayment = $theDonatedItem["plg_DonatedItemOrPayment"];
}

if ($iCurrentFundraiser)
	$_SESSION['iCurrentFundraiser'] = $iCurrentFundraiser;
else
	$iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];

// Get the current fundraiser data
if ($iCurrentFundraiser) {
	$sSQL = "SELECT * from fundraiser_fr WHERE fr_ID = " . $iCurrentFundraiser;
	$rsDeposit = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDeposit));
}

//Set the page title
$sPageTitle = gettext("Donated Item Editor");

//Is this the second pass?
if (isset($_POST["DonatedItemSubmit"]) || isset($_POST["DonatedItemSubmitAndAdd"]))
{
	//Get all the variables from the request object and assign them locally
	$sItem = FilterInput($_POST["Item"]);
	$bMultibuy = FilterInput ($_POST["Multibuy"], 'int');
	$iDonor = FilterInput($_POST["Donor"], 'int');
	$iBuyer = FilterInput($_POST["Buyer"], 'int');
	$sTitle = FilterInput($_POST["Title"]);
	$sDescription = FilterInput($_POST["Description"]);
	$nSellPrice = FilterInput($_POST["SellPrice"]);
	$nEstPrice = FilterInput($_POST["EstPrice"]);
	$nMaterialValue = FilterInput($_POST["MaterialValue"]);
	$nMinimumPrice = FilterInput($_POST["MinimumPrice"]);
	
	if (! $bMultibuy) {
		$bMultibuy = 0;
	}
	if (! $iBuyer) {
		$iBuyer = 0;
	}
	// New DonatedItem or deposit
	if (strlen($iDonatedItemID) < 1)
	{
		$sSQL = "INSERT INTO donateditem_di (di_FR_ID, di_Item, di_multibuy, di_donor_ID, di_buyer_ID, di_title, di_description, di_sellprice, di_estprice, di_materialvalue, di_minimum, di_EnteredBy, di_EnteredDate)
		VALUES (" . $iCurrentFundraiser . ",'" . $sItem . "','" . $bMultibuy . "','" . $iDonor . "','" . $iBuyer . "','" . $sTitle . "','" . $sDescription . "','" . $nSellPrice . "','" . $nEstPrice . "','" . $nMaterialValue . "','".$nMinimumPrice . "'";
		$sSQL .= "," . $_SESSION['iUserID'] . ",'" . date("YmdHis") . "')";
		$bGetKeyBack = True;		
	// Existing record (update)
	} else {
		$sSQL = "UPDATE donateditem_di SET di_FR_ID = " . $iCurrentFundraiser . ", di_Item = '". $sItem . "', di_multibuy = '" . $bMultibuy . "', di_donor_ID = " . $iDonor . ", di_buyer_ID = " . $iBuyer . ", di_title = '" . $sTitle . "', di_description = '" . $sDescription . "', di_sellprice = '" . $nSellPrice . "', di_estprice = '" . $nEstPrice . "', di_materialvalue = '" . $nMaterialValue . "', di_minimum = '" . $nMinimumPrice . "', di_EnteredBy=" . $_SESSION['iUserID'] . ", di_EnteredDate = '" . date("YmdHis") . "'";
		$sSQL .= " WHERE di_ID = " . $iDonatedItemID;
		$bGetKeyBack = false;
	}

	//Execute the SQL
	RunQuery($sSQL);

	// If this is a new DonatedItem or deposit, get the key back
	if ($bGetKeyBack)
	{
		$sSQL = "SELECT MAX(di_ID) AS iDonatedItemID FROM donateditem_di";
		$rsDonatedItemID = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDonatedItemID));
	}

	if (isset($_POST["DonatedItemSubmit"]))
	{
		// Check for redirection to another page after saving information: (ie. DonatedItemEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($linkBack != "") {
			Redirect($linkBack);
		} else {
			//Send to the view of this DonatedItem
			Redirect("DonatedItemEditor.php?DonatedItemID=" . $iDonatedItemID . "&linkBack=", $linkBack);
		}
	}
	else if (isset($_POST["DonatedItemSubmitAndAdd"]))
	{
		//Reload to editor to add another record
		Redirect("DonatedItemEditor.php?CurrentFundraiser=$iCurrentFundraiser&linkBack=", $linkBack);
	}
	
} else {

	//FirstPass
	//Are we editing or adding?
	if (strlen($iDonatedItemID) > 0)
	{
		//Editing....
		//Get all the data on this record

		$sSQL = "SELECT di_ID, di_Item, di_multibuy, di_donor_ID, di_buyer_ID,
		                   a.per_FirstName as donorFirstName, a.per_LastName as donorLastName,
	                       b.per_FirstName as buyerFirstName, b.per_LastName as buyerLastName,
	                       di_title, di_description, di_sellprice, di_estprice, di_materialvalue,
	                       di_minimum
	         FROM donateditem_di
	         LEFT JOIN person_per a ON di_donor_ID=a.per_ID
	         LEFT JOIN person_per b ON di_buyer_ID=b.per_ID
	         WHERE di_ID = '" . $iDonatedItemID . "'"; 
		$rsDonatedItem = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDonatedItem));

		$sItem = $di_Item;
		$bMultibuy = $di_multibuy;
		$iDonor = $di_donor_ID;
		$iBuyer = $di_buyer_ID;
		$sTitle = $di_title;
		$sDescription = $di_description;
		$nSellPrice = $di_sellprice;
		$nEstPrice = $di_estprice;
		$nMaterialValue = $di_materialvalue;
		$nMinimumPrice = $di_minimum;
	}
	else
	{
		//Adding....
		//Set defaults
		$sItem = "";
		$bMultibuy = 0;
		$iDonor = 0;
		$iBuyer = 0;
		$sTitle = "";
		$sDescription = "";
		$nSellPrice = 0.0;
		$nEstPrice = 0.0;
		$nMaterialValue = 0.0;
		$nMinimumPrice = 0.0;
	}
}

// Set Current Deposit setting for user
//if ($iCurrentFundraiser) {
//	$sSQL = "UPDATE user_usr SET usr_CurrentFundraiser = '$iCurrentFundraiser' WHERE usr_per_id = \"".$_SESSION['iUserID']."\"";
//	$rsUpdate = RunQuery($sSQL);
//}

//Get People for the drop-down
$sPeopleSQL = "SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName";

//Get Paddles for the drop-down
$sPaddleSQL = "SELECT pn_ID, pn_Num, pn_per_ID, 
                      a.per_FirstName AS buyerFirstName, 
                      a.per_LastName AS buyerLastName
                      FROM paddlenum_pn
                      LEFT JOIN person_per a on a.per_ID=pn_per_ID
                      WHERE pn_fr_ID=" . $iCurrentFundraiser . " ORDER BY pn_Num";

require "Include/Header.php";

?>

<form method="post" action="DonatedItemEditor.php?<?php echo "CurrentFundraiser=" . $iCurrentFundraiser . "&DonatedItemID=" . $iDonatedItemID . "&linkBack=" . $linkBack; ?>" name="DonatedItemEditor">

<table cellpadding="3" align="center">
	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="DonatedItemSubmit">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save and Add") . "\" name=\"DonatedItemSubmitAndAdd\">"; } ?>
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="DonatedItemCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table border="0" width="100%" cellspacing="0" cellpadding="4">
			<tr>
			<td width="50%" valign="top" align="left">
			<table cellpadding="3">
				<tr>
					<td class="LabelColumn"><?php echo gettext("Item:"); ?></td>
					<td class="TextColumn"><input type="text" name="Item" id="Item" value="<?php echo $sItem; ?>"></td>
				</tr>
				
				<tr>
					<td class="LabelColumn"><?php echo gettext("Multiple items:"); ?></td>
					<td class="TextColumn"><input type="checkbox" name="Multibuy" value="1" <?php if ($bMultibuy) echo " checked";?>><?php echo gettext("Sell to everyone"); ?>
				</tr>

				<tr>
					<td class="LabelColumn"><?php addToolTip("Select the donor from the list."); ?><?php echo gettext("Donor:"); ?>
					</td>
					<td class="TextColumn">
						<select name="Donor">
							<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
							<?php
							$rsPeople = RunQuery($sPeopleSQL);
							while ($aRow = mysql_fetch_array($rsPeople))
							{
								extract($aRow);
								echo "<option value=\"" . $per_ID . "\"";
								if ($iDonor == $per_ID) { echo " selected"; }
								echo ">" . $per_LastName . ", " . $per_FirstName;
								echo " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
							}
							?>
	
						</select>
					</td>
				</tr>
	
				<tr>
					<td class="LabelColumn"><?php echo gettext("Title:"); ?></td>
					<td class="TextColumn"><input type="text" name="Title" id="Title" value="<?php echo $sTitle; ?>"></td>
				</tr>
				
				<tr>
					<td class="LabelColumn"><?php echo gettext("Estimated Price:"); ?></td>
					<td class="TextColumn"><input type="text" name="EstPrice" id="EstPrice" value="<?php echo $nEstPrice; ?>"></td>
				</tr>
				
				<tr>
					<td class="LabelColumn"><?php echo gettext("Material Value:"); ?></td>
					<td class="TextColumn"><input type="text" name="MaterialValue" id="MaterialValue" value="<?php echo $nMaterialValue; ?>"></td>
				</tr>

				<tr>
					<td class="LabelColumn"><?php echo gettext("Minimum Price:"); ?></td>
					<td class="TextColumn"><input type="text" name="MinimumPrice" id="MinimumPrice" value="<?php echo $nMinimumPrice; ?>"></td>
				</tr>
			</table>
			</td>
		
			<td width="50%" valign="top" align="center">
			<table cellpadding="3">
			
				<tr>
					<td class="LabelColumn"><?php addToolTip("Select the buyer from the list."); ?><?php echo gettext("Buyer:"); ?></td>
					<td class="TextColumn">
					    <?php if ($bMultibuy) echo gettext ("Multiple"); else { ?>
						<select name="Buyer">
							<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
							<?php
							$rsBuyers = RunQuery($sPaddleSQL);
							while ($aRow = mysql_fetch_array($rsBuyers))
							{
								extract($aRow);
								echo "<option value=\"" . $pn_per_ID . "\"";
								if ($iBuyer == $pn_per_ID) { echo " selected"; }
								echo ">" . $pn_Num . ":" . $buyerFirstName . " " . $buyerLastName;
							}
					    } ?>
	
						</select>
					</td>
				</tr>
				
				<tr>
					<td class="LabelColumn"><?php echo gettext("Final Price:"); ?></td>
					<td class="TextColumn"><input type="text" name="SellPrice" id="SellPrice" value="<?php echo $nSellPrice; ?>"></td>
				</tr>
			</table>
			</td>
			</tr>
			
			<tr>
			<td width="100%" valign="top" align="left">
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("Description");?></td>
				<td><textarea name="Description" rows="8" cols="90"><?php echo $sDescription?></textarea></td>
			</tr>

			</table>
			</tr>
	</table>

</form>

<?php
require "Include/Footer.php";
?>
