<?php
/*******************************************************************************
*
*  filename    : Reports/USISTAddressReport.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright Contributors
*  description : Creates address verification report
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';
use ChurchCRM\Reports\PDF_AddressReport;

// If user does not have permission redirect to the menu.
if (!$bUSAddressVerification) {
	Redirect('Menu.php');
	exit;
}


// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

if ($_POST['MismatchReport']) {
	$iNum = 1;
	$sWhere = "WHERE fam_Country IN ('United States') ";
	$sMissing = 'Ready for Lookup.  Lookup not done.';
}
elseif ($_POST['NonUSReport']) {
	$iNum = 2;
	$sWhere = "WHERE fam_Country NOT IN ('United States') ";
	$sMissing = 'Unable to perform lookup for non-US address';
} else {
	Redirect('USISTAddressVerification.php');
}

// Instantiate the class and build the report.
$pdf = new PDF_AddressReport();

$sSQL  = 'SELECT * FROM family_fam ';
$sSQL .= $sWhere;
$sSQL .= 'ORDER BY fam_Name';

$rsFamilies = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsFamilies)) {

	extract($aRow);

	$sSQL  = 'SELECT count(lu_fam_ID) AS idexists FROM istlookup_lu ';
	$sSQL .= "WHERE lu_fam_ID IN ($fam_ID)";
	
	$rsLookup = RunQuery($sSQL);
	extract(mysql_fetch_array($rsLookup));
	if ($idexists == '0') {
		$lu_DeliveryLine1 = $sMissing;
		$lu_DeliveryLine2 = '';
		$lu_LastLine = '';
		$lu_ErrorCodes = '';
		$lu_ErrorDesc = '';
	} else {

		$sSQL  = 'SELECT * FROM istlookup_lu ';
		$sSQL .= "WHERE lu_fam_ID IN ($fam_ID)";
		$rsLookup = RunQuery($sSQL);
		extract(mysql_fetch_array($rsLookup));

	}

	// This check alows cities like Coeur d'Alene ID to be accepted also as Coeur d Alene ID
	$lu_LastLine = str_replace("'"," ",$lu_LastLine);
	$fam_City = str_replace("'"," ",$fam_City);		

	// This may not be the best way to handle multiple line addresses
	if (strtoupper($fam_Address2) == strtoupper($lu_DeliveryLine1)) {
		$lu_DeliveryLine1 = $fam_Address1;
		$lu_DeliveryLine2 = $fam_Address2;
	}

	$fam_Str  = '';
	if(strlen($fam_Address1))
		$fam_Str .= $fam_Address1 . "\n";
	if(strlen($fam_Address2))
		$fam_Str .= $fam_Address2 . "\n";
	$fam_Str .= "$fam_City $fam_State $fam_Zip";


	$lu_Str = '';
	$lu_ErrStr = '';
	if(strlen($lu_DeliveryLine1))
		$lu_Str .= $lu_DeliveryLine1 . "\n";
	if(strlen($lu_DeliveryLine2))
		$lu_Str .= $lu_DeliveryLine2 . "\n";
	$lu_Str .= $lu_LastLine;

	$lu_Str = strtoupper($lu_Str);

	if (strtoupper($fam_Str) == $lu_Str) { // Filter nuisance error messages
		if ($lu_ErrorCodes == '10' ||
		    $lu_ErrorCodes == '06' ||
			$lu_ErrorCodes == '14'   ){
			$lu_ErrorCodes = '';
		}
	}

    $bErrorDesc = FALSE;
	if(strlen($lu_ErrorCodes)){
		if($lu_ErrorCodes != 'x1x2'){ // Filter error messages associated with subscribing to
									  // CorrectAddress instead of CorrectAddress with Addons
			$lu_ErrStr = "$lu_ErrorCodes $lu_ErrorDesc";
            $bErrorDesc = TRUE;
		}
	}
    
    $pos1 = strrpos($lu_ErrorDesc, 'no match found');
    $pos2 = strrpos($lu_ErrorDesc, 'not enough information provided');

    if (($pos1 === FALSE) && ($pos2 === FALSE))
        $bErrorDesc = FALSE;

	if ((strtoupper($fam_Str) != $lu_Str) || $bErrorString) {
		// Print both addresses if they don't match exactly

		$fam_Str = $fam_Name . "\n" . $fam_Str;
		$lu_Str = "Intelligent Search Technology, Ltd. Response\n" . $lu_Str;
		$pdf->Add_Record($fam_Str, $lu_Str, $lu_ErrStr);
	}
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate

if ($iPDFOutputType == 1)
	$pdf->Output('Addresses-' . date('Ymd-Gis') . '.pdf', 'D');
else
	$pdf->Output();	

?>
