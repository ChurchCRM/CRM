<?php
/*******************************************************************************
 *
 *  filename    : AddDonors.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2009 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 * 
 * This script adds people who have donated but not registered as buyers to the
 * buyer list so they can get statements too.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$linkBack = "";
if (array_key_exists ("linkBack", $_GET))
	FilterInput($_GET["linkBack"]);
$iFundRaiserID = FilterInput($_GET["FundRaiserID"]);

if ($linkBack == "")
	$linkBack = "PaddleNumList.php?FundRaiserID=$iFundRaiserID";

if ($iFundRaiserID>0) {
	// Get the current fund raiser record
	$sSQL = "SELECT * from fundraiser_fr WHERE fr_ID = " . $iFundRaiserID;
	$rsFRR = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFRR));
	// Set current fundraiser
	$_SESSION['iCurrentFundraiser'] = $iFundRaiserID;
} else {
	redirect ($linkBack);
}

// Get all the people listed as donors for this fundraiser
$sSQL = "SELECT a.per_id as donorID FROM donateditem_di
    	     LEFT JOIN person_per a ON di_donor_ID=a.per_ID
         WHERE di_FR_ID = '" . $iFundRaiserID . "' ORDER BY a.per_id";
$rsDonors = RunQuery($sSQL);

$extraPaddleNum = 1;
$sSQL = "SELECT MAX(pn_NUM) AS pn_max FROM paddlenum_pn WHERE pn_FR_ID = '" . $iFundRaiserID. "'";
$rsMaxPaddle = RunQuery ($sSQL);
if (mysql_num_rows ($rsMaxPaddle) > 0) {
	$oneRow = mysql_fetch_array ($rsMaxPaddle);
	extract ($oneRow);
	$extraPaddleNum = $pn_max + 1;
}

// Go through the donors, add buyer records for any who don't have one yet
while ($donorRow = mysql_fetch_array($rsDonors))
{
	extract($donorRow);

	$sSQL = "SELECT pn_per_id FROM paddlenum_pn WHERE pn_per_id='$donorID' AND pn_FR_ID = '$iFundRaiserID'";
	$rsBuyer = RunQuery($sSQL);
	
	if ($donorID > 0 && mysql_num_rows ($rsBuyer) == 0) {
		$sSQL = "INSERT INTO paddlenum_pn (pn_Num, pn_fr_ID, pn_per_ID)
		                VALUES ('$extraPaddleNum', '$iFundRaiserID', '$donorID')";
		RunQuery($sSQL);
		$extraPaddleNum = $extraPaddleNum + 1;
	}
}
redirect ($linkBack);

?>
