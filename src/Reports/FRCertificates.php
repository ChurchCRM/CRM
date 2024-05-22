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
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Reports\PdfCertificatesReport;
use ChurchCRM\Utils\InputUtils;

if (!isset($_GET['CurrentFundraiser'])) {
    throw new \InvalidArgumentException('Missing required CurrentFundraiser parameter');
}
$iCurrentFundraiser = (int) InputUtils::legacyFilterInput($_GET['CurrentFundraiser'], 'int');

$fundraiser = FundRaiserQuery::create()->findOneById($iCurrentFundraiser);
if ($fundraiser === null) {
    throw new \InvalidArgumentException('No results found for provided CurrentFundraiser parameter');
}

$curY = 0;

// Get all the donated items
$sSQL = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=' . $fundraiser->getId() . ' ORDER BY di_item';
$rsItems = RunQuery($sSQL);

$pdf = new PdfCertificatesReport();
$pdf->SetTitle($fundraiser->getTitle());

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
    if ($per_LastName !== '') {
        $pdf->Write(8, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n\n");
    }
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output('FRCertificates' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
