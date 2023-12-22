<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use ChurchCRM\Service\FinancialService;

$app->group('/payments', function (RouteCollectorProxy $group): void {
    $group->get('/', function (Request $request, Response $response, array $args): Response {
        /** @var FinancialService  $financialService */
        $financialService = $this->get('FinancialService');

        return SlimUtils::renderJSON(
            $response,
            ['payments' => $financialService->getPayments()]
        );
    });

    $group->post('/', function (Request $request, Response $response, array $args): Response {
        $payment = $request->getParsedBody();
        /** @var FinancialService  $financialService */
        $financialService = $this->get('FinancialService');

        return SlimUtils::renderJSON(
            $response,
            ['payment' => $financialService->submitPledgeOrPayment($payment)]
        );
    });

    $group->get('/family/{familyId:[0-9]+}/list', function (Request $request, Response $response, array $args): Response {
        $familyId = SlimUtils::getRouteArgument($request, 'familyId');
        $query = PledgeQuery::create()->filterByFamId($familyId);
        if (!empty(AuthenticationManager::getCurrentUser()->getShowSince())) {
            $query->filterByDate(AuthenticationManager::getCurrentUser()->getShowSince(), Criteria::GREATER_EQUAL);
        }
        if (!AuthenticationManager::getCurrentUser()->isShowPayments()) {
            $query->filterByPledgeOrPayment('Payment', Criteria::NOT_EQUAL);
        }
        if (!AuthenticationManager::getCurrentUser()->isShowPledges()) {
            $query->filterByPledgeOrPayment('Pledge', Criteria::NOT_EQUAL);
        }
        $query->innerJoinDonationFund()->withColumn('donationfund_fun.fun_Name', 'PledgeName');
        $data = $query->find();

        $rows = [];
        foreach ($data as $row) {
            $newRow['FormattedFY'] = $row->getFormattedFY();
            $newRow['GroupKey'] = $row->getGroupKey();
            $newRow['Amount'] = $row->getAmount();
            $newRow['Nondeductible'] = $row->getNondeductible();
            $newRow['Schedule'] = $row->getSchedule();
            $newRow['Method'] = $row->getMethod();
            $newRow['Comment'] = $row->getComment();
            $newRow['PledgeOrPayment'] = $row->getPledgeOrPayment();
            $newRow['Date'] = $row->getDate('Y-m-d');
            $newRow['DateLastEdited'] = $row->getDateLastEdited('Y-m-d');
            $newRow['EditedBy'] = $row->getPerson()->getFullName();
            $newRow['Fund'] = $row->getPledgeName();
            $rows[] = $newRow;
        }

        return SlimUtils::renderJSON($response, ['data' => $rows]);
    });

    $group->delete('/{groupKey}', function (Request $request, Response $response, array $args): Response {
        $groupKey = $args['groupKey'];
        $financialService = $this->get('FinancialService');
        $financialService->deletePayment($groupKey);

        return SlimUtils::renderSuccessJSON($response);
    });
})->add(FinanceRoleAuthMiddleware::class);
