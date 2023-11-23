<?php

/*******************************************************************************
*
*  filename    : Reports/FRCertificates.php
*  last change : 2003-08-30
*  description : Creates a PDF with a silent auction bid sheet for every item.

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PdfCertificatesReport;

$iCurrentFundraiser = $_GET['CurrentFundraiser'];
$curY = 0;

// Get the information about this fundraiser
$sSQL = 'SELECT * FROM fundraiser_fr WHERE fr_ID=' . $iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysqli_fetch_array($rsFR);
extract($thisFR);

// Get all the donated items
$sSQL = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=' . $iCurrentFundraiser . ' ORDER BY di_item';
$rsItems = RunQuery($sSQL);

$pdf = new PdfCertificatesReport();
$pdf->SetTitle($fr_title);

// Loop through items
while ($oneItem = mysqli_fetch_array($rsItems)) {
    extract($oneItem);

    $pdf->addPage();

    $pdf->SetFont('Times', 'B', 24);
    $pdf->Write(8, $di_item . ":\t");
    $pdf->Write(8, stripslashes($di_title) . "\n\n");
    $pdf->SetFont('Times', '', 16);
    $pdf->Write(8, stripslashes($di_description) . "\n");
    if ($di_estprice > 0) {
        $pdf->Write(8, gettext('Estimated value ') . '$' . $di_estprice . '.  ');
    }
    if ($per_LastName != '') {
        $pdf->Write(8, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n\n");
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('FRCertificates' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
