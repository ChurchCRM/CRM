<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\MultibuyQuery;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Reports\PdfCertificatesReport;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Item codes look like "A1", "B23a"; sort by leading letter, then the numeric
// run that follows, then any trailing suffix (mirrors the legacy
// ORDER BY SUBSTR(di_item,1,1), CAST(SUBSTR(di_item,2) AS UNSIGNED), SUBSTR(di_item,4)).
$sortDonatedItemsByItemCode = function (array $items): array {
    usort($items, function ($a, $b) {
        $aItem = (string) $a->getItem();
        $bItem = (string) $b->getItem();
        $cmp = strcmp(substr($aItem, 0, 1), substr($bItem, 0, 1));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = ((int) substr($aItem, 1)) <=> ((int) substr($bItem, 1));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp(substr($aItem, 3), substr($bItem, 3));
    });

    return $items;
};

// Batch-loads the donor Person for each DonatedItem (mirrors the legacy
// LEFT JOIN person_per ON per_ID=di_donor_ID: a missing/zero donor yields nulls).
$loadDonorsById = function (array $donatedItems): array {
    $donorIds = [];
    foreach ($donatedItems as $donatedItem) {
        $donorIds[] = $donatedItem->getDonorId();
    }
    $donors = [];
    foreach (PersonQuery::create()->filterById($donorIds)->find() as $donor) {
        $donors[$donor->getId()] = $donor;
    }

    return $donors;
};

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
$app->get('/{fundraiserId}/reports/bid-sheets', function (Request $request, Response $response, array $args) use ($sortDonatedItemsByItemCode, $loadDonorsById): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Bid sheets are a silent-auction bidding artifact — not applicable to other types.
    if (!in_array($fundraiser->getType() ?: 'Auction', ['Auction', 'Silent Auction'], true)) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    $items = [];
    foreach (DonatedItemQuery::create()->filterByFrId($fundraiser->getId())->find() as $donatedItem) {
        $items[] = $donatedItem;
    }
    $items  = $sortDonatedItemsByItemCode($items);
    $donors = $loadDonorsById($items);

    $pdf = new PdfFRBidSheetsReport();
    $pdf->SetTitle($fundraiser->getTitle());

    foreach ($items as $oneItem) {
        $donor          = $donors[$oneItem->getDonorId()] ?? null;
        $di_item        = $oneItem->getItem();
        $di_title       = $oneItem->getTitle();
        $di_description = $oneItem->getDescription();
        $di_estprice    = $oneItem->getEstprice();
        $di_minimum     = $oneItem->getMinimum();
        $per_LastName   = $donor?->getLastName() ?? '';
        $per_FirstName  = $donor?->getFirstName() ?? '';

        $pdf->addPage();

        $pdf->SetFont('Times', 'B', 24);
        $pdf->Write(5, $di_item . ":\t");
        $pdf->Write(5, stripslashes($di_title) . "\n\n");
        $pdf->SetFont('Times', '', 16);
        $pdf->Write(8, stripslashes($di_description) . "\n");
        if ($di_estprice > 0) {
            $pdf->Write(8, sprintf(gettext('Estimated value $%s.'), $di_estprice) . '  ');
        }
        if ($per_LastName !== '') {
            $pdf->Write(8, sprintf(gettext('Donated by %1$s %2$s.'), $per_FirstName, $per_LastName) . "\n");
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
$app->get('/{fundraiserId}/reports/certificates', function (Request $request, Response $response, array $args) use ($loadDonorsById): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Certificates are a pre-event item display artifact — not applicable to a raffle drawing.
    if (($fundraiser->getType() ?: 'Auction') === 'Raffle') {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    // PdfCertificatesReport::addPage() uses these globals
    global $fr_title, $fr_description, $curY;
    $fr_title       = $fundraiser->getTitle();
    $fr_description = $fundraiser->getDescription();
    $curY           = 0;

    $items = DonatedItemQuery::create()->filterByFrId($fundraiser->getId())->orderByItem()->find();
    $donors = $loadDonorsById(iterator_to_array($items));

    $pdf = new PdfCertificatesReport();
    $pdf->SetTitle($fundraiser->getTitle());

    foreach ($items as $oneItem) {
        $donor          = $donors[$oneItem->getDonorId()] ?? null;
        $di_item        = $oneItem->getItem();
        $di_title       = $oneItem->getTitle();
        $di_description = $oneItem->getDescription();
        $di_estprice    = $oneItem->getEstprice();
        $per_LastName   = $donor?->getLastName() ?? '';
        $per_FirstName  = $donor?->getFirstName() ?? '';

        $pdf->addPage();

        $pdf->SetFont('Times', 'B', 24);
        $pdf->Write(8, $di_item . ":\t");
        $pdf->Write(8, stripslashes($di_title) . "\n\n");
        $pdf->SetFont('Times', '', 16);
        $pdf->Write(8, stripslashes($di_description) . "\n");
        if ($di_estprice > 0) {
            $pdf->Write(8, sprintf(gettext('Estimated value $%s.'), $di_estprice) . '  ');
        }
        if ($per_LastName !== '') {
            $pdf->Write(8, sprintf(gettext('Donated by %1$s %2$s.'), $per_FirstName, $per_LastName) . "\n\n");
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
$app->get('/{fundraiserId}/reports/catalog', function (Request $request, Response $response, array $args) use ($sortDonatedItemsByItemCode, $loadDonorsById): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Catalog is a pre-event browsable bid-display artifact — not applicable to a raffle drawing.
    if (($fundraiser->getType() ?: 'Auction') === 'Raffle') {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    $items = [];
    foreach (DonatedItemQuery::create()->filterByFrId($fundraiser->getId())->find() as $donatedItem) {
        $items[] = $donatedItem;
    }
    $items  = $sortDonatedItemsByItemCode($items);
    $donors = $loadDonorsById($items);

    $pdf = new PdfFRCatalogReport($fundraiser);
    $pdf->SetTitle($fundraiser->getTitle());

    $idFirstChar = '';

    foreach ($items as $oneItem) {
        $donor          = $donors[$oneItem->getDonorId()] ?? null;
        $di_item        = $oneItem->getItem();
        $di_title       = $oneItem->getTitle();
        $di_picture     = $oneItem->getPicture() ?? '';
        $di_description = $oneItem->getDescription();
        $di_minimum     = $oneItem->getMinimum();
        $di_estprice    = $oneItem->getEstprice();
        $per_LastName   = $donor?->getLastName() ?? '';
        $per_FirstName  = $donor?->getFirstName() ?? '';

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
            $pdf->Write(6, sprintf(gettext('Minimum bid $%s.'), $di_minimum) . '  ');
        }
        if ($di_estprice > 0) {
            $pdf->Write(6, sprintf(gettext('Estimated value $%s.'), $di_estprice) . '  ');
        }
        if ($per_LastName !== '') {
            $pdf->Write(6, sprintf(gettext('Donated by %1$s %2$s.'), $per_FirstName, $per_LastName) . "\n");
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

    $iPaddleNumId = (int) ($params['paddleId'] ?? 0);

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $paddleQuery = PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->orderByPnNum();
    if ($iPaddleNumId > 0) {
        $paddleQuery->filterByPnId($iPaddleNumId);
    }
    $paddles  = [];
    $buyerIds = [];
    foreach ($paddleQuery->find() as $paddle) {
        $paddles[]  = $paddle;
        $buyerIds[] = $paddle->getPnPerId();
    }
    $buyers = [];
    foreach (PersonQuery::create()->filterById($buyerIds)->find() as $buyer) {
        $buyers[$buyer->getId()] = $buyer;
    }

    $pdf = new PdfFundRaiserStatement();

    foreach ($paddles as $paddle) {
        $pn_ID          = $paddle->getPnId();
        $pn_Num         = $paddle->getPnNum();
        $pn_per_ID      = $paddle->getPnPerId();
        $paddleBuyer    = $buyers[$pn_per_ID] ?? null;
        $paddleFirstName = $paddleBuyer?->getFirstName() ?? '';
        $paddleLastName  = $paddleBuyer?->getLastName() ?? '';
        $paddleFamily    = $paddleBuyer?->getFamily();
        $fam_ID          = $paddleFamily?->getId() ?? 0;
        $fam_Name        = $paddleFamily?->getName() ?? '';
        $fam_Address1    = $paddleFamily?->getAddress1() ?? '';
        $fam_Address2    = $paddleFamily?->getAddress2() ?? '';
        $fam_City        = (string) ($paddleFamily?->getCity() ?? '');
        $fam_State       = (string) ($paddleFamily?->getState() ?? '');
        $fam_Zip         = $paddleFamily?->getZip() ?? '';
        $fam_Country     = $paddleFamily?->getCountry() ?? '';

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

            $donatedItems     = DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByDonorId($pn_per_ID)->find();
            $donatedItemBuyerIds = [];
            foreach ($donatedItems as $donatedItem) {
                $donatedItemBuyerIds[] = $donatedItem->getBuyerId();
            }
            $donatedItemBuyers = [];
            foreach (PersonQuery::create()->filterById($donatedItemBuyerIds)->find() as $itemBuyer) {
                $donatedItemBuyers[$itemBuyer->getId()] = $itemBuyer;
            }

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

            foreach ($donatedItems as $donatedItem) {
                $di_item      = $donatedItem->getItem();
                $di_title     = $donatedItem->getTitle();
                $di_sellprice = $donatedItem->getSellprice();
                $itemBuyer      = $donatedItemBuyers[$donatedItem->getBuyerId()] ?? null;
                $buyerFirstName = $itemBuyer?->getFirstName() ?? '';
                $buyerLastName  = $itemBuyer?->getLastName() ?? '';
                $buyerEmail     = $itemBuyer?->getEmail() ?? '';
                $buyerPhone     = $itemBuyer?->getFamily()?->getHomePhone() ?? '';

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

            $purchasedItems      = DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByBuyerId($pn_per_ID)->find();
            $purchasedItemDonorIds = [];
            foreach ($purchasedItems as $purchasedItem) {
                $purchasedItemDonorIds[] = $purchasedItem->getDonorId();
            }
            $purchasedItemDonors = [];
            foreach (PersonQuery::create()->filterById($purchasedItemDonorIds)->find() as $itemDonor) {
                $purchasedItemDonors[$itemDonor->getId()] = $itemDonor;
            }

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

            foreach ($purchasedItems as $purchasedItem) {
                $di_item        = $purchasedItem->getItem();
                $di_title       = $purchasedItem->getTitle();
                $di_sellprice   = $purchasedItem->getSellprice();
                $itemDonor      = $purchasedItemDonors[$purchasedItem->getDonorId()] ?? null;
                $donorFirstName = $itemDonor?->getFirstName() ?? '';
                $donorLastName  = $itemDonor?->getLastName() ?? '';
                $donorEmail     = $itemDonor?->getEmail() ?? '';
                $donorPhone     = $itemDonor?->getFamily()?->getHomePhone() ?? '';

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

            // Left join replicated: a multibuy row whose item isn't in this fundraiser is excluded
            // below, mirroring the legacy WHERE b.di_FR_ID=$fundraiserId filter on the LEFT JOIN.
            $multibuyRows = MultibuyQuery::create()->filterByMbPerId($pn_per_ID)->find();
            $multibuyItemIds = [];
            foreach ($multibuyRows as $multibuyRow) {
                $multibuyItemIds[] = $multibuyRow->getMbItemId();
            }
            $multibuyItems = [];
            foreach (DonatedItemQuery::create()->filterById($multibuyItemIds)->filterByFrId($fundraiserId)->find() as $multibuyItem) {
                $multibuyItems[$multibuyItem->getId()] = $multibuyItem;
            }
            $multibuyDonorIds = [];
            foreach ($multibuyItems as $multibuyItem) {
                $multibuyDonorIds[] = $multibuyItem->getDonorId();
            }
            $multibuyDonors = [];
            foreach (PersonQuery::create()->filterById($multibuyDonorIds)->find() as $multibuyDonor) {
                $multibuyDonors[$multibuyDonor->getId()] = $multibuyDonor;
            }

            foreach ($multibuyRows as $multibuyRow) {
                $multibuyItem = $multibuyItems[$multibuyRow->getMbItemId()] ?? null;
                if ($multibuyItem === null) {
                    continue;
                }
                $mb_count       = $multibuyRow->getMbCount();
                $multibuyDonor  = $multibuyDonors[$multibuyItem->getDonorId()] ?? null;
                $donorFirstName = $multibuyDonor?->getFirstName() ?? '';
                $donorLastName  = $multibuyDonor?->getLastName() ?? '';
                $donorEmail     = $multibuyDonor?->getEmail() ?? '';
                $donorPhone     = $multibuyDonor?->getFamily()?->getHomePhone() ?? '';
                $di_item        = $multibuyItem->getItem();
                $di_title       = $multibuyItem->getTitle();
                $di_sellprice   = $multibuyItem->getSellprice();

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
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, '-----------------------------------------------------------------------------------------------------------------------------------------------');
            $curY += 2 * SystemConfig::getValue('incrementY');
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Buyer #') . ' ' . $pn_Num . ' : ' . $paddleFirstName . ' ' . $paddleLastName . ' : ' . gettext('Total purchases: $') . $totalAmount . ' : ' . gettext('Amount paid: ________________'));
            $curY += 2 * SystemConfig::getValue('incrementY');
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Paid by (  ) Cash    (  ) Check    (  ) Credit card __ __ __ __    __ __ __ __    __ __ __ __    __ __ __ __  Exp __ / __'));
            $curY += 2 * SystemConfig::getValue('incrementY');
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, str_repeat(' ', 40) . gettext('Signature') . ' ' . str_repeat('_', 68));

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
