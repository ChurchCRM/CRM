<?php

/*******************************************************************************
*
*  filename    : Reports/FRBidSheets.php
*  last change : 2003-08-30
*  description : Creates a PDF with a silent auction bid sheet for every item.

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;

$iCurrentFundraiser = $_GET['CurrentFundraiser'];

class PdfFRBidSheetsReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(15, 25);

        $this->SetAutoPageBreak(true, 25);
    }

    public function addPage($orientation = '', $format = '', $rotation = 0): void
    {
        global $fr_title, $fr_description;

        parent::addPage($orientation, $format, $rotation);

        //      $this->SetFont("Times",'B',16);
//      $this->Write (8, $fr_title."\n");
        //      $curY += 8;
        //      $this->Write (8, $fr_description."\n\n");
        //      $curY += 8;
        //      $this->SetFont("Times",'',10);
    }
}

// Get the information about this fundraiser
$sSQL = 'SELECT * FROM fundraiser_fr WHERE fr_ID=' . $iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysqli_fetch_array($rsFR);
extract($thisFR);

// Get all the donated items
$sSQL = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID ' .
        ' WHERE di_FR_ID=' . $iCurrentFundraiser .
        ' ORDER BY SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)';

$rsItems = RunQuery($sSQL);

$pdf = new PdfFRBidSheetsReport();
$pdf->SetTitle($fr_title);

// Loop through items
while ($oneItem = mysqli_fetch_array($rsItems)) {
    extract($oneItem);

    $pdf->addPage();

    $pdf->SetFont('Times', 'B', 24);
    $pdf->Write(5, $di_item . ":\t");
    $pdf->Write(5, stripslashes($di_title) . "\n\n");
    $pdf->SetFont('Times', '', 16);
    $pdf->Write(8, stripslashes($di_description) . "\n");
    if ($di_estprice > 0) {
        $pdf->Write(8, gettext('Estimated value ') . '$' . $di_estprice . '.  ');
    }
    if ($per_LastName != '') {
        $pdf->Write(8, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n");
    }
    $pdf->Write(8, "\n");

    $widName = 100;
    $widPaddle = 30;
    $widBid = 40;
    $lineHeight = 7;

    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell($widName, $lineHeight, gettext('Name'), 1, 0);
    $pdf->Cell($widPaddle, $lineHeight, gettext('Paddle'), 1, 0);
    $pdf->Cell($widBid, $lineHeight, gettext('Bid'), 1, 1);

    if ($di_minimum > 0) {
        $pdf->Cell($widName, $lineHeight, '', 1, 0);
        $pdf->Cell($widPaddle, $lineHeight, '', 1, 0);
        $pdf->Cell($widBid, $lineHeight, '$' . $di_minimum, 1, 1);
    }
    for ($i = 0; $i < 20; $i += 1) {
        $pdf->Cell($widName, $lineHeight, '', 1, 0);
        $pdf->Cell($widPaddle, $lineHeight, '', 1, 0);
        $pdf->Cell($widBid, $lineHeight, '', 1, 1);
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('FRBidSheets' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
