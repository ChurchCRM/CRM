<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\DepositService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/deposit', function (RouteCollectorProxy $group): void {

    /**
     * GET /finance/deposit/search
     *
     * Deposit search page — replaces legacy FindDepositSlip.php.
     * Accepts filter query parameters; results are rendered server-side into a
     * DataTable so the URL always reflects current filter state.
     *
     * Query parameters (all optional):
     *   fyid        int    Fiscal-year ID (0 = All Time; omitted = current FY)
     *   dateStart   Y-m-d  Earliest deposit date (inclusive; overridden by fyid when fyid>0)
     *   dateEnd     Y-m-d  Latest deposit date (inclusive; overridden by fyid when fyid>0)
     *   depositId   int    Exact deposit ID
     *   closed      0|1    Deposit status (0=open, 1=closed, ''=all)
     *   enteredBy   int    Teller person ID
     *   fundId      int    Donation fund ID
     *   amountMin   float  Minimum total deposit amount
     *   amountMax   float  Maximum total deposit amount
     */
    $group->get('/search', function (Request $request, Response $response): Response {
        $depositService   = new DepositService();
        $financialService = new FinancialService();
        $queryParams      = $request->getQueryParams();

        $currentFyid  = FiscalYearUtils::getCurrentFiscalYearId();
        // Default to current FY when no fyid param is present; 0 = All Time.
        $selectedFyid = isset($queryParams['fyid']) ? (int) $queryParams['fyid'] : $currentFyid;

        $availableYears = $financialService->getAvailableDepositFiscalYears();

        $filters = [
            'fyid'       => $selectedFyid,
            'dateStart'  => $queryParams['dateStart'] ?? '',
            'dateEnd'    => $queryParams['dateEnd'] ?? '',
            'depositId'  => $queryParams['depositId'] ?? '',
            'closed'     => $queryParams['closed'] ?? '',
            'enteredBy'  => $queryParams['enteredBy'] ?? '',
            'fundId'     => $queryParams['fundId'] ?? '',
            'amountMin'  => $queryParams['amountMin'] ?? '',
            'amountMax'  => $queryParams['amountMax'] ?? '',
        ];

        $deposits = $depositService->searchDeposits($filters);

        // Active donation funds for the filter dropdown
        $funds = DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByOrder()
            ->find();

        // Build teller list from all unique enteredby IDs via a raw SQL query,
        // bypassing DepositQuery::preSelect() (which adds pledge JOIN + GROUP BY
        // and would conflict with a simple DISTINCT select).
        $con        = Propel::getConnection();
        $stmt       = $con->prepare('SELECT DISTINCT dep_EnteredBy FROM deposit_dep WHERE dep_EnteredBy IS NOT NULL AND dep_EnteredBy > 0 ORDER BY dep_EnteredBy');
        $stmt->execute();
        $tellerIds  = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $stmt->closeCursor();
        $tellerList = [];
        if (!empty($tellerIds)) {
            $persons = PersonQuery::create()->filterById($tellerIds)->find();
            foreach ($persons as $person) {
                $tellerList[(int) $person->getId()] = trim($person->getFirstName() . ' ' . $person->getLastName());
            }
        }
        asort($tellerList); // alphabetical by name

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'      => SystemURLs::getRootPath(),
            'sPageTitle'     => gettext('Deposits'),
            'sPageSubtitle'  => gettext('Search and manage deposit slip records'),
            'aBreadcrumbs'   => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Deposits')],
            ]),
            'deposits'       => $deposits,
            'funds'          => $funds,
            'tellerList'     => $tellerList,
            'filters'        => $filters,
            'availableYears' => $availableYears,
            'selectedFyid'   => $selectedFyid,
            'currentFyid'    => $currentFyid,
        ];

        return $renderer->render($response, 'deposits/search.php', $pageArgs);
    });

    /**
     * GET /finance/deposit/{id}
     *
     * Deposit editor — replaces legacy DepositSlipEditor.php. Unknown ids go
     * back to the search page. Date, comment and status are saved through
     * POST /api/deposits/{id}.
     */
    $group->get('/{id:\d+}', function (Request $request, Response $response, array $args): Response {
        $depositId = (int) $args['id'];
        $deposit   = DepositQuery::create()->findOneById($depositId);

        if ($deposit === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/finance/deposit/search')
                ->withStatus(302);
        }

        // The payment editor reads this to preselect the method for a new payment.
        $defaultMethods = ['Bank' => 'CHECK', 'CreditCard' => 'CREDITCARD', 'BankDraft' => 'BANKDRAFT'];
        if (isset($defaultMethods[$deposit->getType()])) {
            $_SESSION['idefaultPaymentMethod'] = $defaultMethods[$deposit->getType()];
        }

        $_SESSION['iCurrentDeposit'] = $depositId;
        $currentUser = AuthenticationManager::getCurrentUser();
        $currentUser->setCurrentDeposit($depositId);
        $currentUser->save();

        $fundTotals = $deposit->getFundTotals()->toArray();

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'deposits/editor.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => $deposit->getType() . ' ' . gettext('Deposit Slip Number') . ': ' . $depositId,
            'sPageSubtitle' => gettext('View and manage deposit slip payments'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Deposits'), '/finance/deposit/search'],
                [gettext('Edit Deposit')],
            ]),
            'deposit'       => $deposit,
            'depositId'     => $depositId,
            'prevDeposit'   => Deposit::getPreviousDeposit($depositId),
            'nextDeposit'   => Deposit::getNextDeposit($depositId),
            'fundLabels'    => array_column($fundTotals, 'Name'),
            'fundData'      => array_map(static fn ($fund): float => (float) $fund['Total'], $fundTotals),
        ]);
    });

});
