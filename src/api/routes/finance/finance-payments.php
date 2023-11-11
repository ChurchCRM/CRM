<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/payments', function () use ($app) {
    $app->get('/', function (Request $request, Response $response, array $args) use ($app) {
        $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments());
    });

    $app->post('/', function ($request, $response, $args) use ($app) {
        $payment = $request->getParsedBody();
        echo json_encode(array('payment' => $app->FinancialService->submitPledgeOrPayment($payment)), JSON_THROW_ON_ERROR);
    });

    $app->get('/family/{familyId:[0-9]+}/list', function (Request $request, Response $response, array $args) {
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

        $rows = array();

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

        return $response->withJson(array('data' => $rows));
    });

    $app->delete('/{groupKey}', function (Request $request, Response $response, array $args) use ($app) {
        $groupKey = $args['groupKey'];
        $app->FinancialService->deletePayment($groupKey);
        echo json_encode(array('status' => 'ok'));
    });
})->add(new FinanceRoleAuthMiddleware());
