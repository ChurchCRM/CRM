<?php

use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\PledgeQuery;
use ChurchCRM\SessionUser;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/payments', function () {
    $this->get('/', function (Request $request, Response $response, array $args) {
        $this->FinancialService->getPaymentJSON($this->FinancialService->getPayments());
    });

    $this->post('/', function ($request, $response, $args) {
        $payment = $request->getParsedBody();
        echo json_encode(['payment' => $this->FinancialService->submitPledgeOrPayment($payment)]);
    });

    $this->get('/family/{familyId:[0-9]+}/list', function (Request $request, Response $response, array $args) {
        $familyId = $request->getAttribute("route")->getArgument("familyId");
        $query = PledgeQuery::create()->filterByFamId($familyId);
        if (!empty(SessionUser::getUser()->getShowSince())) {
            $query->filterByDate(SessionUser::getUser()->getShowSince(), Criteria::GREATER_EQUAL);
        }
        if (!SessionUser::getUser()->isShowPayments()) {
            $query->filterByPledgeOrPayment("Payment", Criteria::NOT_EQUAL);
        }
        if (!SessionUser::getUser()->isShowPledges()) {
            $query->filterByPledgeOrPayment("Pledge", Criteria::NOT_EQUAL);
        }
        $data = $query->find();

        return $response->withJson(["data" => $data->toArray()]);

    });

    $this->delete('/{groupKey}', function (Request $request, Response $response, array $args) {
        $groupKey = $args['groupKey'];
        $this->FinancialService->deletePayment($groupKey);
        echo json_encode(['status' => 'ok']);
    });
})->add(new FinanceRoleAuthMiddleware());
