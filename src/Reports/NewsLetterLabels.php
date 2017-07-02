<?php
/*******************************************************************************
*
*  filename    : Reports/NewsLetterLabels.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the newletter mailing labels
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

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PDF_NewsletterLabels;
use ChurchCRM\Utils\InputUtils;

$sLabelFormat = InputUtils::LegacyFilterInput($_GET['labeltype']);
setcookie('labeltype', $sLabelFormat, time() + 60 * 60 * 24 * 90, '/');

// Instantiate the directory class and build the report.
$pdf = new PDF_NewsletterLabels($sLabelFormat);

$sFontInfo = FontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], time() + 60 * 60 * 24 * 90, '/');
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, time() + 60 * 60 * 24 * 90, '/');
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);
if ($sFontSize != 'default') {
    $pdf->Set_Char_Size($sFontSize);
}

// Get all the families which receive the newsletter by mail
$sSQL = "SELECT * FROM family_fam WHERE fam_SendNewsLetter='TRUE' ORDER BY fam_Zip";
$rsFamilies = RunQuery($sSQL);

// Loop through families
$labelThisPage = 0;
$labelHeight = 26.5;
$labelLineHeight = 6;
$labelX = 10;

while ($aFam = mysqli_fetch_array($rsFamilies)) {
    extract($aFam);

    $labelText = $pdf->MakeSalutation($fam_ID);
    if ($fam_Address1 != '') {
        $labelText .= "\n".$fam_Address1;
    }
    if ($fam_Address2 != '') {
        $labelText .= "\n".$fam_Address2;
    }
    $labelText .= sprintf("\n%s, %s  %s", $fam_City, $fam_State, $fam_Zip);

    if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
        $labelText .= "\n".$fam_Country;
    }

    $pdf->Add_PDF_Label($labelText);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('NewsLetterLabels'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
