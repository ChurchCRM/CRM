<?php
/*******************************************************************************
 *
 *  filename    : DonatedItemEditor.php
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

$iDonatedItemID = FilterInputArr($_GET, "DonatedItemID", 'int');
$linkBack = FilterInputArr($_GET, "linkBack");
$iCurrentFundraiser = FilterInputArr($_GET, "CurrentFundraiser");

if ($iDonatedItemID > 0) {
  $sSQL = "SELECT * FROM donateditem_di WHERE di_ID = '$iDonatedItemID'";
  $rsDonatedItem = RunQuery($sSQL);
  $theDonatedItem = mysql_fetch_array($rsDonatedItem);
  $iCurrentFundraiser = $theDonatedItem["di_FR_ID"];
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
if (isset($_POST["DonatedItemSubmit"]) || isset($_POST["DonatedItemSubmitAndAdd"])) {
  //Get all the variables from the request object and assign them locally
  $sItem = FilterInputArr($_POST, "Item");
  $bMultibuy = FilterInputArr($_POST, "Multibuy", 'int');
  $iDonor = FilterInputArr($_POST, "Donor", 'int');
  $iBuyer = FilterInputArr($_POST, "Buyer", 'int');
  $sTitle = FilterInputArr($_POST, "Title");
  $sDescription = FilterInputArr($_POST, "Description");
  $nSellPrice = FilterInputArr($_POST, "SellPrice");
  $nEstPrice = FilterInputArr($_POST, "EstPrice");
  $nMaterialValue = FilterInputArr($_POST, "MaterialValue");
  $nMinimumPrice = FilterInputArr($_POST, "MinimumPrice");
  $sPictureURL = FilterInputArr($_POST, "PictureURL");

  if (!$bMultibuy) {
    $bMultibuy = 0;
  }
  if (!$iBuyer) {
    $iBuyer = 0;
  }
  // New DonatedItem or deposit
  if (strlen($iDonatedItemID) < 1) {
    $sSQL = "INSERT INTO donateditem_di (di_FR_ID, di_Item, di_multibuy, di_donor_ID, di_buyer_ID, di_title, di_description, di_sellprice, di_estprice, di_materialvalue, di_minimum, di_picture, di_EnteredBy, di_EnteredDate)
		VALUES (" . $iCurrentFundraiser . ",'" . $sItem . "','" . $bMultibuy . "','" . $iDonor . "','" . $iBuyer . "','" . html_entity_decode($sTitle) . "','" . html_entity_decode($sDescription) . "','" . $nSellPrice . "','" . $nEstPrice . "','" . $nMaterialValue . "','" . $nMinimumPrice . "','" . mysql_real_escape_string($sPictureURL) . "'";
    $sSQL .= "," . $_SESSION['iUserID'] . ",'" . date("YmdHis") . "')";
    $bGetKeyBack = True;
    // Existing record (update)
  }
  else {
    $sSQL = "UPDATE donateditem_di SET di_FR_ID = " . $iCurrentFundraiser . ", di_Item = '" . $sItem . "', di_multibuy = '" . $bMultibuy . "', di_donor_ID = " . $iDonor . ", di_buyer_ID = " . $iBuyer . ", di_title = '" . html_entity_decode($sTitle) . "', di_description = '" . html_entity_decode($sDescription) . "', di_sellprice = '" . $nSellPrice . "', di_estprice = '" . $nEstPrice . "', di_materialvalue = '" . $nMaterialValue . "', di_minimum = '" . $nMinimumPrice . "', di_picture = '" . mysql_real_escape_string($sPictureURL) . "', di_EnteredBy=" . $_SESSION['iUserID'] . ", di_EnteredDate = '" . date("YmdHis") . "'";
    $sSQL .= " WHERE di_ID = " . $iDonatedItemID;
    echo "<br><br><br><br><br><br>" . $sSQL;
    $bGetKeyBack = false;
  }

  //Execute the SQL
  RunQuery($sSQL);

  // If this is a new DonatedItem or deposit, get the key back
  if ($bGetKeyBack) {
    $sSQL = "SELECT MAX(di_ID) AS iDonatedItemID FROM donateditem_di";
    $rsDonatedItemID = RunQuery($sSQL);
    extract(mysql_fetch_array($rsDonatedItemID));
  }

  if (isset($_POST["DonatedItemSubmit"])) {
    // Check for redirection to another page after saving information: (ie. DonatedItemEditor.php?previousPage=prev.php?a=1;b=2;c=3)
    if ($linkBack != "") {
      Redirect($linkBack);
    }
    else {
      //Send to the view of this DonatedItem
      Redirect("DonatedItemEditor.php?DonatedItemID=" . $iDonatedItemID . "&linkBack=", $linkBack);
    }
  }
  else if (isset($_POST["DonatedItemSubmitAndAdd"])) {
    //Reload to editor to add another record
    Redirect("DonatedItemEditor.php?CurrentFundraiser=$iCurrentFundraiser&linkBack=", $linkBack);
  }
}
else {

  //FirstPass
  //Are we editing or adding?
  if (strlen($iDonatedItemID) > 0) {
    //Editing....
    //Get all the data on this record

    $sSQL = "SELECT di_ID, di_Item, di_multibuy, di_donor_ID, di_buyer_ID,
		                   a.per_FirstName as donorFirstName, a.per_LastName as donorLastName,
	                       b.per_FirstName as buyerFirstName, b.per_LastName as buyerLastName,
	                       di_title, di_description, di_sellprice, di_estprice, di_materialvalue,
	                       di_minimum, di_picture
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
    $sPictureURL = $di_picture;
  }
  else {
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
    $sPictureURL = "";
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


<div class="box box-body">
  <form method="post" action="DonatedItemEditor.php?<?= "CurrentFundraiser=" . $iCurrentFundraiser . "&DonatedItemID=" . $iDonatedItemID . "&linkBack=" . $linkBack ?>" name="DonatedItemEditor">
    <table cellpadding="3" align="center"> <!-- Table for the whole form -->
      <tr> <!-- Row of buttons across the top -->
        <td align="center">
          <input type="submit" class="btn" value="<?= gettext("Save") ?>" name="DonatedItemSubmit">
<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"btn\" value=\"" . gettext("Save and Add") . "\" name=\"DonatedItemSubmitAndAdd\">"; } ?>
          <input type="button" class="btn" value="<?= gettext("Cancel") ?>" name="DonatedItemCancel" onclick="javascript:document.location = '<?php if (strlen($linkBack) > 0) { echo $linkBack; }
else { echo "Menu.php"; } ?>';">
        </td>
      </tr>

      <tr> <!-- Remaining stuff below the buttons -->
        <td>
          <table border="0" width="100%" cellspacing="0" cellpadding="4"> <!-- Table for the left side entries -->
            <tr>
              <td width="50%" valign="top" align="left">
                <table cellpadding="3">
                  <tr>
                    <td class="LabelColumn"><?= gettext("Item:") ?></td>
                    <td class="TextColumn"><input type="text" name="Item" id="Item" value="<?= $sItem ?>"></td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Multiple items:") ?></td>
                    <td class="TextColumn"><input type="checkbox" name="Multibuy" value="1" <?php if ($bMultibuy) echo " checked"; ?>><?= gettext("Sell to everyone") ?>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Donor:") ?>
                    </td>
                    <td class="TextColumn">
                      <select name="Donor">
                        <option value="0" selected><?= gettext("Unassigned") ?></option>
<?php
$rsPeople = RunQuery($sPeopleSQL);
while ($aRow = mysql_fetch_array($rsPeople)) {
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
                    <td class="LabelColumn"><?= gettext("Title:") ?></td>
                    <td class="TextColumn"><input type="text" name="Title" id="Title" value="<?= htmlentities($sTitle) ?>"/></td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Estimated Price:") ?></td>
                    <td class="TextColumn"><input type="text" name="EstPrice" id="EstPrice" value="<?= $nEstPrice ?>"></td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Material Value:") ?></td>
                    <td class="TextColumn"><input type="text" name="MaterialValue" id="MaterialValue" value="<?= $nMaterialValue ?>"></td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Minimum Price:") ?></td>
                    <td class="TextColumn"><input type="text" name="MinimumPrice" id="MinimumPrice" value="<?= $nMinimumPrice ?>"></td>
                  </tr>
                </table> <!-- Table for the left side entries -->
              </td>

              <td width="50%" valign="top" align="center"> <!-- Cross over to the right side of the main form -->
                <table cellpadding="3"> <!-- Table for the right side entries -->

                  <tr>
                    <td class="LabelColumn"><?= gettext("Buyer:") ?></td>
                    <td class="TextColumn">
<?php if ($bMultibuy) echo gettext("Multiple"); else { ?>
                        <select name="Buyer">
                          <option value="0" selected><?= gettext("Unassigned") ?></option>
  <?php
  $rsBuyers = RunQuery($sPaddleSQL);
  while ($aRow = mysql_fetch_array($rsBuyers)) {
    extract($aRow);
    echo "<option value=\"" . $pn_per_ID . "\"";
    if ($iBuyer == $pn_per_ID) { echo " selected"; }
    echo ">" . $pn_Num . ":" . $buyerFirstName . " " . $buyerLastName;
  }
}
?>

                      </select>
                    </td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Final Price:") ?></td>
                    <td class="TextColumn"><input type="text" name="SellPrice" id="SellPrice" value="<?= $nSellPrice ?>"></td>
                  </tr>

                  <tr><td>&nbsp;</td></tr> <!-- Make an empty row to segregate the replication controls -->

                  <tr>
                    <td class="LabelColumn"><?= gettext("Replicate item") ?></td>
                    <td class="TextColumn"><input type="text" name="NumberCopies" id="NumberCopies" value="0"></td>
                    <td><input type="button" class="btn" value="<?= gettext("Go") ?>" name="DonatedItemReplicate" onclick="javascript:document.location = 'DonatedItemReplicate.php?DonatedItemID=<?= $iDonatedItemID ?>&Count=' + NumberCopies.value"></td>
                  </tr>

                </table>

              </td> <!-- Close the right side entries -->
            </tr> <!-- Close the part of the form with left and right entries -->

            <tr>
              <td colspan="2" width="100%" valign="top" align="left"> <!-- Larger entries get more space across the bottom -->
                <table cellpadding="3"> <!-- Table for the bottom full-width entries -->

                  <tr>
                    <td class="LabelColumn"><?= gettext("Description") ?></td>
                    <td><textarea name="Description" rows="8" cols="90"><?= htmlentities($sDescription) ?></textarea></td>
                  </tr>

                  <tr>
                    <td class="LabelColumn"><?= gettext("Picture URL") ?></td>
                    <td><textarea name="PictureURL" rows="1" cols="90"><?= htmlentities($sPictureURL) ?></textarea></td>
                  </tr>

<?php if ($sPictureURL != "") { ?>
                    <tr>
                      <td colspan="2" width="100%"><img src="<?= htmlentities($sPictureURL) ?>"/></td>
                    </tr>
<?php } ?>

                </table> <!-- Table for the bottom full-width entries -->
              </td>
            </tr>
          </table> <!-- Table for the whole form -->
    
    </table>
  </form>
  <div>
<?php require "Include/Footer.php"; ?>
