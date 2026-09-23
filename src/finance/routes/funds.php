<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Web route for the Donation Funds admin page.
 * GET /finance/funds — renders a Tabler DataTable page (Finance role or Admin,
 * enforced by FinanceRoleAuthMiddleware at the module level in finance/index.php).
 */
$app->get('/funds', function (Request $request, Response $response): Response {
    $service = new DonationFundService();
    $funds = $service->getAllFunds();

    // Build per-fund pledge-existence map for delete-disable UI
    $fundIds = [];
    foreach ($funds as $fund) {
        $fundIds[] = (int) $fund->getId();
    }
    $pledgedFundIds = [];
    if (!empty($fundIds)) {
        // Single query: get distinct fund IDs that have at least one pledge
        // 'FundId' is the Propel phpName for plg_fundID (schema.xml)
        $pledgesRaw = PledgeQuery::create()
            ->select('FundId')
            ->filterByFundId($fundIds)
            ->groupByFundId()
            ->find()
            ->getData();
        foreach ($pledgesRaw as $fundId) {
            $pledgedFundIds[(int) $fundId] = true;
        }
    }

    // Build enriched fund data for the view
    $fundsData = [];
    $totalCount = $funds->count();
    $i = 0;
    foreach ($funds as $fund) {
        $id = (int) $fund->getId();
        $fundsData[] = [
            'id'          => $id,
            'name'        => $fund->getName(),
            'description' => $fund->getDescription(),
            'active'      => $fund->getActive() === 'true',
            'order'       => (int) $fund->getOrder(),
            'hasPledges'  => isset($pledgedFundIds[$id]),
            'isFirst'     => $i === 0,
            'isLast'      => $i === ($totalCount - 1),
        ];
        $i++;
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Donation Funds'),
        'sPageSubtitle' => gettext('Manage donation funds for financial tracking'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Donation Funds')],
        ]),
        'fundsData'     => $fundsData,
    ];

    return $renderer->render($response, 'funds/index.php', $pageArgs);
});
