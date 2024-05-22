<?php

/*******************************************************************************
*
*  filename    : Reports/FundRaiserStatement.php
*  last change : 2009-04-17
*  description : Creates a PDF with one or more fund raiser statements
*  copyright   : Copyright 2009 Michael Wilt

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;

$iPaddleNumID = InputUtils::legacyFilterInputArr($_GET, 'PaddleNumID', 'int');
$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

//Get the paddlenum records for this fundraiser
if ($iPaddleNumID > 0) {
    $selectOneCrit = ' AND pn_ID=' . $iPaddleNumID . ' ';
} else {
    $selectOneCrit = '';
}

$sSQL = 'SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                a.per_FirstName as paddleFirstName, a.per_LastName as paddleLastName, a.per_Email as paddleEmail,
				b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country
         FROM paddlenum_pn
         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
         LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID
         WHERE pn_FR_ID =' . $iFundRaiserID . $selectOneCrit . ' ORDER BY pn_Num';
$rsPaddleNums = RunQuery($sSQL);

class PdfFundRaiserStatement extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(20, 20);

        $this->SetAutoPageBreak(false);
    }

    public function startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country): float
    {
        global $letterhead;
        $curY = $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);

        return $curY;
    }

    public function finishPage($curY): void
    {
    }

    public function cellWithWrap($curY, $curNewY, $ItemWid, $tableCellY, $txt, $bdr, $aligncode)
    {
        $curPage = $this->PageNo();
        $leftX = $this->GetX();
        $this->SetXY($leftX, $curY);
        $this->MultiCell($ItemWid, $tableCellY, $txt, $bdr, $aligncode);
        $newY = $this->GetY();
        $newPage = $this->PageNo();
        $this->SetXY($leftX + $ItemWid, $curY);
        if ($newPage > $curPage) {
            return $newY;
        } else {
            return max($newY, $curNewY);
        }
    }
}

// Instantiate the directory class and build the report.
$pdf = new PdfFundRaiserStatement();

// Loop through result array
while ($row = mysqli_fetch_array($rsPaddleNums)) {
    extract($row);

    // If running for a specific paddle just proceed
    // If running for all paddles check the _POST to see which ones are selected
    if ($iPaddleNumID || isset($_POST["Chk$pn_ID"])) {
        // Start page for this paddle number
        $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);

        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Donated Items:'));
        $curY += 2 * SystemConfig::getValue('incrementY');

        $ItemWid = 10;
        $QtyWid = 10;
        $TitleWid = 50;
        $DonorWid = 30;
        $EmailWid = 40;
        $PhoneWid = 24;
        $PriceWid = 20;
        $tableCellY = 4;

        // Get donated items and make the table
        $sSQL = 'SELECT di_item, di_title, di_buyer_id, di_sellprice,
		                a.per_FirstName as buyerFirstName,
		                a.per_LastName as buyerLastName,
		                a.per_Email as buyerEmail,
		                b.fam_homephone as buyerPhone
		                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_buyer_id
		                                    LEFT JOIN family_fam b on a.per_fam_id = b.fam_id
		                WHERE di_FR_ID = ' . $iFundRaiserID . ' AND di_donor_id = ' . $pn_per_ID;
        $rsDonatedItems = RunQuery($sSQL);

        $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
        $pdf->SetFont('Times', 'B', 10);

        $pdf->Cell($ItemWid, $tableCellY, 'Item');
        $pdf->Cell($TitleWid, $tableCellY, 'Name');
        $pdf->Cell($DonorWid, $tableCellY, 'Buyer');
        $pdf->Cell($PhoneWid, $tableCellY, 'Phone');
        $pdf->Cell($EmailWid, $tableCellY, 'Email');
        $pdf->Cell($PriceWid, $tableCellY, 'Amount', 0, 1, 'R');
        $curY = $pdf->GetY();
        $pdf->SetFont('Times', '', 10);

        while ($itemRow = mysqli_fetch_array($rsDonatedItems)) {
            extract($itemRow);
            $nextY = $curY;
            $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
            $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, $di_title, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $buyerFirstName . ' ' . $buyerLastName, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $buyerPhone, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $buyerEmail, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, $di_sellprice, 0, 'R');
            $curY = $nextY;
        }

        // Get purchased items and make the table
        $curY += 2 * $tableCellY;
        $pdf->SetFont('Times', '', 10);
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Purchased Items:'));
        $curY += 2 * SystemConfig::getValue('incrementY');

        $totalAmount = 0.0;

        // Get individual auction items first
        $sSQL = 'SELECT di_item, di_title, di_donor_id, di_sellprice,
		                a.per_FirstName as donorFirstName,
		                a.per_LastName as donorLastName,
		                a.per_Email as donorEmail,
		                b.fam_homePhone as donorPhone
		                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_donor_id
		                                    LEFT JOIN family_fam b on a.per_fam_id=b.fam_id
		                WHERE di_FR_ID = ' . $iFundRaiserID . ' AND di_buyer_id = ' . $pn_per_ID;
        $rsPurchasedItems = RunQuery($sSQL);

        $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell($ItemWid, $tableCellY, 'Item');
        $pdf->Cell($QtyWid, $tableCellY, 'Qty');
        $pdf->Cell($TitleWid, $tableCellY, 'Name');
        $pdf->Cell($DonorWid, $tableCellY, 'Donor');
        $pdf->Cell($PhoneWid, $tableCellY, 'Phone');
        $pdf->Cell($EmailWid, $tableCellY, 'Email');
        $pdf->Cell($PriceWid, $tableCellY, 'Amount', 0, 1, 'R');
        $pdf->SetFont('Times', '', 10);
        $curY += SystemConfig::getValue('incrementY');

        while ($itemRow = mysqli_fetch_array($rsPurchasedItems)) {
            extract($itemRow);
            $nextY = $curY;
            $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
            $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $QtyWid, $tableCellY, '1', 0, 'L'); // quantity 1 for all individual items
            $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, $di_title, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $donorFirstName . ' ' . $donorLastName, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $donorPhone, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $donorEmail, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, '$' . $di_sellprice, 0, 'R');
            $curY = $nextY;
            $totalAmount += $di_sellprice;
        }

        // Get multibuy items for this buyer
        $sqlMultiBuy = <<<SQL
SELECT
    mb_count,
    a.per_FirstName as donorFirstName,
    a.per_LastName as donorLastName,
    a.per_Email as donorEmail,
    c.fam_HomePhone as donorPhone,
    b.di_item,
    b.di_title,
    b.di_sellprice
FROM multibuy_mb
LEFT JOIN donateditem_di b ON mb_item_ID=b.di_ID
LEFT JOIN person_per a ON b.di_donor_id=a.per_ID
LEFT JOIN family_fam c ON a.per_fam_id = c.fam_ID
WHERE b.di_FR_ID=$iFundRaiserID AND mb_per_ID=$pn_per_ID;
SQL;
        $rsMultiBuy = RunQuery($sqlMultiBuy);
        while ($mbRow = mysqli_fetch_array($rsMultiBuy)) {
            $mb_count = $mbRow['mb_count'];
            $donorFirstName = $mbRow['donorFirstName'];
            $donorLastName = $mbRow['donorLastName'];
            $donorEmail = $mbRow['donorEmail'];
            $donorPhone = $mbRow['donorPhone'];
            $di_item = $mbRow['di_item'];
            $di_title = $mbRow['di_title'];
            $di_sellprice = $mbRow['di_sellprice'];

            $nextY = $curY;
            $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
            $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $QtyWid, $tableCellY, $mb_count, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, stripslashes($di_title), 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $donorFirstName . ' ' . $donorLastName, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $donorPhone, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $donorEmail, 0, 'L');
            $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, '$' . ($mb_count * $di_sellprice), 0, 'R');
            $curY = $nextY;
            $totalAmount += $mb_count * $di_sellprice;
        }

        // Report total purchased items
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Total of all purchases: $') . $totalAmount);
        $curY += 2 * SystemConfig::getValue('incrementY');

        // Make the tear-off record for the bottom of the page
        $curY = 240;
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('-----------------------------------------------------------------------------------------------------------------------------------------------'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Buyer # ') . $pn_Num . ' : ' . $paddleFirstName . ' ' . $paddleLastName . ' : ' . gettext('Total purchases: $') . $totalAmount . ' : ' . gettext('Amount paid: ________________'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Paid by (  ) Cash    (  ) Check    (  ) Credit card __ __ __ __    __ __ __ __    __ __ __ __    __ __ __ __  Exp __ / __'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('                                        Signature ________________________________________________________________'));

        $pdf->finishPage($curY);
    }
}

$pdf->Output('FundRaiserStatement' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
