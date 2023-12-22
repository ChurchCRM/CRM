<?php

/*******************************************************************************
*
*  filename    : Reports/FRCatalog.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the confirmation letters asking member
*                families to verify the information in the database.

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;

$iCurrentFundraiser = $_GET['CurrentFundraiser'];

$curY = 0;

class PdfFRCatalogReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);

        $this->addPage();
        $this->SetAutoPageBreak(true, 25);
    }

    public function addPage($orientation = '', $format = '', $rotation = 0): void
    {
        global $fr_title, $fr_description, $curY;

        parent::addPage($orientation, $format, $rotation);

        $this->SetFont('Times', 'B', 16);
        $this->Write(8, $fr_title . "\n");
        $curY += 8;
        $this->Write(8, $fr_description . "\n\n");
        $curY += 8;
        $this->SetFont('Times', '', 12);
    }
}

// Get the information about this fundraiser
$sSQL = 'SELECT * FROM fundraiser_fr WHERE fr_ID=' . $iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysqli_fetch_array($rsFR);
extract($thisFR);

// Get all the donated items
$sSQL = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=' . $iCurrentFundraiser .
' ORDER BY SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)';
$rsItems = RunQuery($sSQL);

$pdf = new PdfFRCatalogReport();
$pdf->SetTitle($fr_title);

// Loop through items
$idFirstChar = '';

while ($oneItem = mysqli_fetch_array($rsItems)) {
    extract($oneItem);

    $newIdFirstChar = mb_substr($di_item, 0, 1);
    $maxYNewPage = 220;
    if ($di_picture != '') {
        $maxYNewPage = 120;
    }
    if ($pdf->GetY() > $maxYNewPage || ($idFirstChar != '' && $idFirstChar != $newIdFirstChar)) {
        $pdf->addPage();
    }
    $idFirstChar = $newIdFirstChar;

    $pdf->SetFont('Times', 'B', 12);
    $pdf->Write(6, $di_item . ': ');
    $pdf->Write(6, stripslashes($di_title) . "\n");

    if ($di_picture != '') {
        $s = getimagesize($di_picture);
        $h = (100.0 / $s[0]) * $s[1];
        $pdf->Image($di_picture, $pdf->GetX(), $pdf->GetY(), 100.0, $h);
        $pdf->SetY($pdf->GetY() + $h);
    }

    $pdf->SetFont('Times', '', 12);
    $pdf->Write(6, stripslashes($di_description) . "\n");
    if ($di_minimum > 0) {
        $pdf->Write(6, gettext('Minimum bid ') . '$' . $di_minimum . '.  ');
    }
    if ($di_estprice > 0) {
        $pdf->Write(6, gettext('Estimated value ') . '$' . $di_estprice . '.  ');
    }
    if ($per_LastName != '') {
        $pdf->Write(6, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n");
    }
    $pdf->Write(6, "\n");
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('FRCatalog' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
