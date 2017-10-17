<?php
/*******************************************************************************
*
*  filename    : Reports/ConfimLabels.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the mailing labels for the confirm data letter

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PDF_Label;
use ChurchCRM\Utils\InputUtils;

$sLabelFormat = InputUtils::LegacyFilterInput($_GET['labeltype']);
setcookie('labeltype', $sLabelFormat, time() + 60 * 60 * 24 * 90, '/');

$pdf = new PDF_Label($sLabelFormat);

$sFontInfo = FontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], time() + 60 * 60 * 24 * 90, '/');
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, time() + 60 * 60 * 24 * 90, '/');
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);
if ($sFontSize != 'default') {
    $pdf->Set_Char_Size($sFontSize);
}

// Get all the families
$sSQL = 'SELECT * FROM family_fam WHERE 1 ORDER BY fam_Name';
$rsFamilies = RunQuery($sSQL);

// Loop through families
while ($aFam = mysqli_fetch_array($rsFamilies)) {
    extract($aFam);

    $labelStr = $pdf->MakeSalutation($fam_ID);
    if ($fam_Address1 != '') {
        $labelStr .= "\n".$fam_Address1;
    }
    if ($fam_Address2 != '') {
        $labelStr .= "\n".$fam_Address2;
    }
    $labelStr .= sprintf("\n%s, %s  %s", $fam_City, $fam_State, $fam_Zip);
    if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
        $labelStr .= "\n".$fam_Country;
    }
    $pdf->Add_PDF_Label($labelStr);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ConfirmDataLabels'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
