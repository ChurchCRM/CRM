<?php
/*******************************************************************************
 *
 *  filename    : DonatedItemReplicate.php
 *  last change : 2015-01-01
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2015 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];
$iDonatedItemID = InputUtils::LegacyFilterInputArr($_GET, 'DonatedItemID', 'int');
$iCount = InputUtils::LegacyFilterInputArr($_GET, 'Count', 'int');

$sLetter = 'a';

$sSQL = "SELECT di_item FROM donateditem_di WHERE di_ID=$iDonatedItemID";
$rsItem = RunQuery($sSQL);
$row = mysqli_fetch_array($rsItem);
$startItem = $row[0];

if (strlen($startItem) == 2) { // replicated items will sort better if they have a two-digit number
    $letter = mb_substr($startItem, 0, 1);
    $number = mb_substr($startItem, 1, 1);
    $startItem = $letter.'0'.$number;
}

$letterNum = ord('a');

for ($i = 0; $i < $iCount; $i++) {
    $sSQL = 'INSERT INTO donateditem_di (di_item,di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,di_EnteredBy,di_EnteredDate,di_picture)';
    $sSQL .= "SELECT '".$startItem.chr($letterNum)."',di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,";
    $sSQL .= AuthenticationManager::GetCurrentUser()->getId().",'".date('YmdHis')."',";
    $sSQL .= 'di_picture';
    $sSQL .= " FROM donateditem_di WHERE di_ID=$iDonatedItemID";
    $ret = RunQuery($sSQL);
    $letterNum += 1;
}
RedirectUtils::Redirect("FundRaiserEditor.php?FundRaiserID=$iFundRaiserID");
