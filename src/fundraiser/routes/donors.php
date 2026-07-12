<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/{fundraiserId}/donors — confirmation page (migrated from AddDonors.php)
$app->get('/{fundraiserId}/donors', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'donors.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Add Donors to Buyer List'),
        'sPageSubtitle' => gettext('Auto-assign paddle numbers to all item donors'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Add Donors'), '/fundraiser/' . $fundraiserId . '/editor'],
            [gettext('Add to Buyer List')],
        ]),
        'fundraiserId' => $fundraiserId,
        'fundraiser'   => $fundraiser,
    ]);
});

// POST /fundraiser/{fundraiserId}/donors — add donor paddle numbers (migrated from AddDonors.php)
$app->post('/{fundraiserId}/donors', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    if ($fundraiserId <= 0) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    // Get all people listed as donors for this fundraiser
    $sSQL = "SELECT a.per_id as donorID FROM donateditem_di
             LEFT JOIN person_per a ON di_donor_ID=a.per_ID
             WHERE di_FR_ID = '" . $fundraiserId . "' ORDER BY a.per_id";
    $rsDonors = RunQuery($sSQL);

    // Get the next paddle number to use
    $extraPaddleNum = 1;
    $sSQL = "SELECT MAX(pn_NUM) AS pn_max FROM paddlenum_pn WHERE pn_FR_ID = '" . $fundraiserId . "'";
    $rsMaxPaddle = RunQuery($sSQL);
    if (mysqli_num_rows($rsMaxPaddle) > 0) {
        $oneRow = mysqli_fetch_array($rsMaxPaddle);
        $pn_max = $oneRow['pn_max'];
        $extraPaddleNum = (int) $pn_max + 1;
    }

    // For each donor who does not yet have a paddle number, add one
    while ($donorRow = mysqli_fetch_array($rsDonors)) {
        $donorID = $donorRow['donorID'];

        $sSQL = "SELECT pn_per_id FROM paddlenum_pn WHERE pn_per_id='$donorID' AND pn_FR_ID = '$fundraiserId'";
        $rsBuyer = RunQuery($sSQL);

        if ($donorID > 0 && mysqli_num_rows($rsBuyer) === 0) {
            $sSQL = "INSERT INTO paddlenum_pn (pn_Num, pn_fr_ID, pn_per_ID)
                     VALUES ('$extraPaddleNum', '$fundraiserId', '$donorID')";
            RunQuery($sSQL);
            $extraPaddleNum++;
        }
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')
        ->withStatus(302);
});
