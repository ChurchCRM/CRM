<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/payments', function (RouteCollectorProxy $group) {
    $group->get('/', function (Request $request, Response $response, array $args) {
        $financialService = $this->get('FinancialService');
        $response->getBody()->write(json_encode($financialService->getPaymentJSON($financialService->getPayments())));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/', function (Request $request, Response $response, array $args) {
        $payment = $request->getParsedBody();
        $financialService = $this->get('FinancialService');

        echo json_encode(
            ['payment' => $financialService->submitPledgeOrPayment($payment)],
            JSON_THROW_ON_ERROR
        );
    });

    $group->get('/family/{familyId:[0-9]+}/list', function (Request $request, Response $response, array $args) {
        $familyId = $request->getAttribute('route')->getArgument('familyId');
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
            array_push($rows, $newRow);
        }

        $response->getBody()->write(json_encode(['data' => $rows]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->delete('/{groupKey}', function (Request $request, Response $response, array $args) {
        $groupKey = $args['groupKey'];
        $financialService = $this->get('FinancialService');
        $financialService->deletePayment($groupKey);
        echo json_encode(['status' => 'ok']);
    });
})->add(FinanceRoleAuthMiddleware::class);
