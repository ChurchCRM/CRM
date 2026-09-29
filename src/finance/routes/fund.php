<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyPledgeSummaryService;
use ChurchCRM\Service\FundContributorsService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/fund', function (RouteCollectorProxy $group): void {

    /**
     * Fund contributor drill-down page.
     * GET /finance/fund/{fundId}/contributors[?fyid=N]
     *
     * Shows all families that pledged/paid to a specific donation fund in a given fiscal year.
     */
    $group->get('/{fundId:[0-9]+}/contributors', function (Request $request, Response $response, array $args): Response {
        $fundId = (int) $args['fundId'];

        $famPledgeService = new FamilyPledgeSummaryService();
        $service = new FundContributorsService();

        $queryParams = $request->getQueryParams();
        $fyid = isset($queryParams['fyid']) ? (int) $queryParams['fyid'] : $famPledgeService->getCurrentFiscalYearId();

        $data = $service->getFundContributors($fundId, $fyid);

        if ($data['fund'] === null) {
            return $response->withStatus(404);
        }

        $availableYears = $famPledgeService->getAvailableFiscalYears();
        $currentFyid    = $famPledgeService->getCurrentFiscalYearId();

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $fundName = $data['fund']['name'];

        $pageArgs = [
            'sRootPath'      => SystemURLs::getRootPath(),
            'sPageTitle'     => $fundName,
            'sPageSubtitle'  => gettext('Fund contributor pledge summary by fiscal year'),
            'aBreadcrumbs'   => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Pledge Dashboard'), '/finance/pledge/dashboard'],
                [$fundName],
            ]),
            'fund'           => $data['fund'],
            'contributors'   => $data['contributors'],
            'stats'          => $data['stats'],
            'availableYears' => $availableYears,
            'selectedFyid'   => $fyid,
            'currentFyid'    => $currentFyid,
        ];

        return $renderer->render($response, 'pledges/contributors.php', $pageArgs);
    });
});
