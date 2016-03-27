<?php
/*******************************************************************************
 *
 *  filename    : DonatedItemReplicate.php
 *  last change : 2015-01-01
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2015 Michael Wilt
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

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];
$iDonatedItemID = FilterInputArr($_GET,"DonatedItemID", "int");
$iCount = FilterInputArr($_GET,"Count", "int");

$sLetter = 'a';

$sSQL = "SELECT di_item FROM donateditem_di WHERE di_ID=$iDonatedItemID";
$rsItem = RunQuery ($sSQL);
$row = mysql_fetch_array($rsItem);
$startItem = $row[0];

if (strlen($startItem) == 2) { // replicated items will sort better if they have a two-digit number
	$letter = substr ($startItem, 0, 1);
	$number = substr ($startItem, 1, 1);
	$startItem = $letter . '0' . $number;
}

$letterNum = ord ("a");

for ($i = 0; $i < $iCount; $i++) {
	$sSQL = "INSERT INTO donateditem_di (di_item,di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,di_EnteredBy,di_EnteredDate,di_picture)";
	$sSQL .= "SELECT '" . $startItem . chr($letterNum) . "',di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,";
	$sSQL .= $_SESSION['iUserID'] . ",'" . date("YmdHis") . "',";
	$sSQL .= "di_picture";
	$sSQL .= " FROM donateditem_di WHERE di_ID=$iDonatedItemID";
	$ret = RunQuery ($sSQL);
	$letterNum += 1;
}
Redirect ("FundRaiserEditor.php?FundRaiserID=$iFundRaiserID");
?>
