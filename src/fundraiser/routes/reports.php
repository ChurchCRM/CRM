<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Reports\PdfCertificatesReport;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ---------------------------------------------------------------------------
// PDF class: Bid Sheets (migrated from src/Reports/FRBidSheets.php)
// ---------------------------------------------------------------------------
class PdfFRBidSheetsReport extends ChurchInfoReport
{
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(15, 25);
        $this->SetAutoPageBreak(true, 25);
    }
}

// ---------------------------------------------------------------------------
// PDF class: Catalog (migrated from src/Reports/FRCatalog.php)
// ---------------------------------------------------------------------------
class PdfFRCatalogReport extends ChurchInfoReport
{
    public int $curY = 0;
    private \ChurchCRM\model\ChurchCRM\FundRaiser $fundraiser;

    public function __construct(\ChurchCRM\model\ChurchCRM\FundRaiser $fundraiser)
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->fundraiser = $fundraiser;
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);
        $this->addPage();
        $this->SetAutoPageBreak(true, 25);
    }

    public function addPage($orientation = '', $size = '', $rotation = 0): void
    {
        parent::addPage($orientation, $size, $rotation);
        $this->SetFont('Times', 'B', 16);
        $this->Write(8, $this->fundraiser->getTitle() . "\n");
        $this->curY += 8;
        $this->Write(8, $this->fundraiser->getDescription() . "\n\n");
        $this->curY += 8;
        $this->SetFont('Times', '', 12);
    }
}

// ---------------------------------------------------------------------------
// PDF class: Statement (migrated from src/Reports/FundRaiserStatement.php)
// ---------------------------------------------------------------------------
class PdfFundRaiserStatement extends ChurchInfoReport
{
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

        return $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
    }

    public function finishPage($curY): void
    {
    }

    public function cellWithWrap($curY, $curNewY, $ItemWid, $tableCellY, $txt, $bdr, $aligncode)
    {
        $curPage = $this->PageNo();
        $leftX   = $this->GetX();
        $this->SetXY($leftX, $curY);
        $this->MultiCell($ItemWid, $tableCellY, $txt, $bdr, $aligncode);
        $newY    = $this->GetY();
        $newPage = $this->PageNo();
        $this->SetXY($leftX + $ItemWid, $curY);
        if ($newPage > $curPage) {
            return $newY;
        }

        return max($newY, $curNewY);
    }
}

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/bid-sheets — PDF bid sheets
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/bid-sheets', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $sSQL    = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID'
        . ' WHERE di_FR_ID=' . $fundraiser->getId()
        . ' ORDER BY SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)';
    $rsItems = RunQuery($sSQL);

    $pdf = new PdfFRBidSheetsReport();
    $pdf->SetTitle($fundraiser->getTitle());

    while ($oneItem = mysqli_fetch_array($rsItems)) {
        $di_item        = $oneItem['di_item'];
        $di_title       = $oneItem['di_title'];
        $di_description = $oneItem['di_description'];
        $di_estprice    = $oneItem['di_estprice'];
        $di_minimum     = $oneItem['di_minimum'];
        $per_LastName   = $oneItem['per_LastName'];
        $per_FirstName  = $oneItem['per_FirstName'];

        $pdf->addPage();

        $pdf->SetFont('Times', 'B', 24);
        $pdf->Write(5, $di_item . ":\t");
        $pdf->Write(5, stripslashes($di_title) . "\n\n");
        $pdf->SetFont('Times', '', 16);
        $pdf->Write(8, stripslashes($di_description) . "\n");
        if ($di_estprice > 0) {
            $pdf->Write(8, gettext('Estimated value ') . '$' . $di_estprice . '.  ');
        }
        if ($per_LastName !== '') {
            $pdf->Write(8, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n");
        }
        $pdf->Write(8, "\n");

        $widName    = 100;
        $widPaddle  = 30;
        $widBid     = 40;
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
        for ($i = 0; $i < 20; $i++) {
            $pdf->Cell($widName, $lineHeight, '', 1, 0);
            $pdf->Cell($widPaddle, $lineHeight, '', 1, 0);
            $pdf->Cell($widBid, $lineHeight, '', 1, 1);
        }
    }

    $fileName   = 'FRBidSheets' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $pdfContent = $pdf->Output($fileName, 'S');
    $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
    $response->getBody()->write($pdfContent);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition . '; filename="' . $fileName . '"');
});

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/certificates — PDF certificates
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/certificates', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // PdfCertificatesReport::addPage() uses these globals
    global $fr_title, $fr_description, $curY;
    $fr_title       = $fundraiser->getTitle();
    $fr_description = $fundraiser->getDescription();
    $curY           = 0;

    $sSQL    = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=' . $fundraiser->getId() . ' ORDER BY di_item';
    $rsItems = RunQuery($sSQL);

    $pdf = new PdfCertificatesReport();
    $pdf->SetTitle($fundraiser->getTitle());

    while ($oneItem = mysqli_fetch_array($rsItems)) {
        $di_item        = $oneItem['di_item'];
        $di_title       = $oneItem['di_title'];
        $di_description = $oneItem['di_description'];
        $di_estprice    = $oneItem['di_estprice'];
        $per_LastName   = $oneItem['per_LastName'];
        $per_FirstName  = $oneItem['per_FirstName'];

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

    $fileName   = 'FRCertificates' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $pdfContent = $pdf->Output($fileName, 'S');
    $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
    $response->getBody()->write($pdfContent);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition . '; filename="' . $fileName . '"');
});

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/catalog — PDF catalog
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/catalog', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $sSQL    = 'SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=' . $fundraiser->getId()
        . ' ORDER BY SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)';
    $rsItems = RunQuery($sSQL);

    $pdf = new PdfFRCatalogReport($fundraiser);
    $pdf->SetTitle($fundraiser->getTitle());

    $idFirstChar = '';

    while ($oneItem = mysqli_fetch_array($rsItems)) {
        $di_item        = $oneItem['di_item'];
        $di_title       = $oneItem['di_title'];
        $di_picture     = $oneItem['di_picture'];
        $di_description = $oneItem['di_description'];
        $di_minimum     = $oneItem['di_minimum'];
        $di_estprice    = $oneItem['di_estprice'];
        $per_LastName   = $oneItem['per_LastName'];
        $per_FirstName  = $oneItem['per_FirstName'];

        $newIdFirstChar = mb_substr($di_item, 0, 1);
        $maxYNewPage    = 220;
        if ($di_picture !== '') {
            $maxYNewPage = 120;
        }
        if ($pdf->GetY() > $maxYNewPage || ($idFirstChar !== '' && $idFirstChar !== $newIdFirstChar)) {
            $pdf->addPage();
        }
        $idFirstChar = $newIdFirstChar;

        $pdf->SetFont('Times', 'B', 12);
        $pdf->Write(6, $di_item . ': ');
        $pdf->Write(6, stripslashes($di_title) . "\n");

        if ($di_picture !== '') {
            $s = getimagesize($di_picture);
            if ($s !== false && $s[0] > 0) {
                $h = (100.0 / $s[0]) * $s[1];
                $pdf->Image($di_picture, $pdf->GetX(), $pdf->GetY(), 100.0, $h);
                $pdf->SetY($pdf->GetY() + $h);
            }
        }

        $pdf->SetFont('Times', '', 12);
        $pdf->Write(6, stripslashes($di_description) . "\n");
        if ($di_minimum > 0) {
            $pdf->Write(6, gettext('Minimum bid ') . '$' . $di_minimum . '.  ');
        }
        if ($di_estprice > 0) {
            $pdf->Write(6, gettext('Estimated value ') . '$' . $di_estprice . '.  ');
        }
        if ($per_LastName !== '') {
            $pdf->Write(6, gettext('Donated by ') . $per_FirstName . ' ' . $per_LastName . ".\n");
        }
        $pdf->Write(6, "\n");
    }

    $fileName   = 'FRCatalog' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $pdfContent = $pdf->Output($fileName, 'S');
    $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
    $response->getBody()->write($pdfContent);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition . '; filename="' . $fileName . '"');
});

// ---------------------------------------------------------------------------
// GET+POST /fundraiser/{fundraiserId}/reports/statement — PDF statements
// GET: single paddle (?paddleId=X query param)
// POST: selected paddles from PaddleNumList form (Chk* body params)
// ---------------------------------------------------------------------------
$app->map(['GET', 'POST'], '/{fundraiserId}/reports/statement', function (Request $request, Response $response, array $args): Response {
    global $letterhead;
    $letterhead = ''; // default: no custom letterhead

    $fundraiserId = (int) $args['fundraiserId'];
    $params       = $request->getQueryParams();
    $body         = (array) $request->getParsedBody();

    // CSRF guard: POST (bulk-select form) carries a token; GET (single-paddle link) is read-only.
    if ($request->getMethod() === 'POST') {
        if (!CSRFUtils::verifyRequest($body, 'statement_report')) {
            $response->getBody()->write(gettext('Invalid security token. Please try again.'));
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
    }

    $iPaddleNumId = (int) ($params['paddleId'] ?? 0);

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    if ($iPaddleNumId > 0) {
        $selectOneCrit = ' AND pn_ID=' . $iPaddleNumId . ' ';
    } else {
        $selectOneCrit = '';
    }

    $sSQL = 'SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                    a.per_FirstName as paddleFirstName, a.per_LastName as paddleLastName, a.per_Email as paddleEmail,
                    b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country
             FROM paddlenum_pn
             LEFT JOIN person_per a ON pn_per_ID=a.per_ID
             LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID
             WHERE pn_FR_ID =' . $fundraiserId . $selectOneCrit . ' ORDER BY pn_Num';
    $rsPaddleNums = RunQuery($sSQL);

    $pdf = new PdfFundRaiserStatement();

    while ($row = mysqli_fetch_array($rsPaddleNums)) {
        $pn_ID          = $row['pn_ID'];
        $pn_Num         = $row['pn_Num'];
        $pn_per_ID      = $row['pn_per_ID'];
        $paddleFirstName = $row['paddleFirstName'];
        $paddleLastName  = $row['paddleLastName'];
        $fam_ID          = $row['fam_ID'];
        $fam_Name        = $row['fam_Name'];
        $fam_Address1    = $row['fam_Address1'];
        $fam_Address2    = $row['fam_Address2'];
        $fam_City        = (string) $row['fam_City'];
        $fam_State       = (string) $row['fam_State'];
        $fam_Zip         = $row['fam_Zip'];
        $fam_Country     = $row['fam_Country'];

        // If running for a specific paddle, always include; otherwise check POST checkboxes
        if ($iPaddleNumId || isset($body["Chk$pn_ID"])) {
            $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);

            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Donated Items') . ':');
            $curY += 2 * SystemConfig::getValue('incrementY');

            $ItemWid    = 10;
            $QtyWid     = 10;
            $TitleWid   = 50;
            $DonorWid   = 30;
            $EmailWid   = 40;
            $PhoneWid   = 24;
            $PriceWid   = 20;
            $tableCellY = 4;

            $sSQL2 = 'SELECT di_item, di_title, di_buyer_id, di_sellprice,
                             a.per_FirstName as buyerFirstName,
                             a.per_LastName as buyerLastName,
                             a.per_Email as buyerEmail,
                             b.fam_homephone as buyerPhone
                      FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_buyer_id
                                          LEFT JOIN family_fam b on a.per_fam_id = b.fam_id
                      WHERE di_FR_ID = ' . $fundraiserId . ' AND di_donor_id = ' . (int) $pn_per_ID;
            $rsDonatedItems = RunQuery($sSQL2);

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
                $di_item      = $itemRow['di_item'];
                $di_title     = $itemRow['di_title'];
                $di_sellprice = $itemRow['di_sellprice'];
                $buyerFirstName = $itemRow['buyerFirstName'];
                $buyerLastName  = $itemRow['buyerLastName'];
                $buyerEmail     = $itemRow['buyerEmail'];
                $buyerPhone     = $itemRow['buyerPhone'];

                $nextY = $curY;
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, $di_title, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $buyerFirstName . ' ' . $buyerLastName, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $buyerPhone, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $buyerEmail, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, $di_sellprice, 0, 'R');
                $curY  = $nextY;
            }

            $curY += 2 * $tableCellY;
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Purchased Items') . ':');
            $curY += 2 * SystemConfig::getValue('incrementY');

            $totalAmount = 0.0;

            $sSQL3 = 'SELECT di_item, di_title, di_donor_id, di_sellprice,
                             a.per_FirstName as donorFirstName,
                             a.per_LastName as donorLastName,
                             a.per_Email as donorEmail,
                             b.fam_homePhone as donorPhone
                      FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_donor_id
                                          LEFT JOIN family_fam b on a.per_fam_id=b.fam_id
                      WHERE di_FR_ID = ' . $fundraiserId . ' AND di_buyer_id = ' . (int) $pn_per_ID;
            $rsPurchasedItems = RunQuery($sSQL3);

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
                $di_item        = $itemRow['di_item'];
                $di_title       = $itemRow['di_title'];
                $di_sellprice   = $itemRow['di_sellprice'];
                $donorFirstName = $itemRow['donorFirstName'];
                $donorLastName  = $itemRow['donorLastName'];
                $donorEmail     = $itemRow['donorEmail'];
                $donorPhone     = $itemRow['donorPhone'];

                $nextY = $curY;
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $QtyWid, $tableCellY, '1', 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, $di_title, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $donorFirstName . ' ' . $donorLastName, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $donorPhone, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $donorEmail, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, '$' . $di_sellprice, 0, 'R');
                $curY  = $nextY;
                $totalAmount += (float) $di_sellprice;
            }

            $iPnPerID    = (int) $pn_per_ID;
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
WHERE b.di_FR_ID=$fundraiserId AND mb_per_ID=$iPnPerID;
SQL;
            $rsMultiBuy = RunQuery($sqlMultiBuy);
            while ($mbRow = mysqli_fetch_array($rsMultiBuy)) {
                $mb_count       = $mbRow['mb_count'];
                $donorFirstName = $mbRow['donorFirstName'];
                $donorLastName  = $mbRow['donorLastName'];
                $donorEmail     = $mbRow['donorEmail'];
                $donorPhone     = $mbRow['donorPhone'];
                $di_item        = $mbRow['di_item'];
                $di_title       = $mbRow['di_title'];
                $di_sellprice   = $mbRow['di_sellprice'];

                $nextY = $curY;
                $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
                $nextY = $pdf->cellWithWrap($curY, $nextY, $ItemWid, $tableCellY, $di_item, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $QtyWid, $tableCellY, $mb_count, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $TitleWid, $tableCellY, stripslashes($di_title), 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $DonorWid, $tableCellY, $donorFirstName . ' ' . $donorLastName, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PhoneWid, $tableCellY, $donorPhone, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $EmailWid, $tableCellY, $donorEmail, 0, 'L');
                $nextY = $pdf->cellWithWrap($curY, $nextY, $PriceWid, $tableCellY, '$' . ($mb_count * $di_sellprice), 0, 'R');
                $curY  = $nextY;
                $totalAmount += $mb_count * (float) $di_sellprice;
            }

            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Total of all purchases: $') . $totalAmount);
            $curY += 2 * SystemConfig::getValue('incrementY');

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

    $fileName    = 'FundRaiserStatement' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $pdfContent  = $pdf->Output($fileName, 'S');
    $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
    $response->getBody()->write($pdfContent);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition . '; filename="' . $fileName . '"');
});
