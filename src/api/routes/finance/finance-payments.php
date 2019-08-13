<?php

use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app->group('/payments', function () {
    $this->get('/', function ($request, $response, $args) {
        $this->FinancialService->getPaymentJSON($this->FinancialService->getPayments());
    });

    $this->post('/', function ($request, $response, $args) {
        $payment = $request->getParsedBody();
        echo json_encode(['payment' => $this->FinancialService->submitPledgeOrPayment($payment)]);
    });
    // udpate required here!
    $this->delete('/{groupKey}', function ($request, $response, $args) {
        $familyID = $args['FamilyID'];
        $TypeOfMbr = $args['TypeOfMbr'];
        $TypeOfMbr = $args['DepositID'];
        $this->FinancialService->deletePayment($familyID, $TypeOfMbr, $depositID);
        echo json_encode(['status' => 'ok']);
    });
})->add(new FinanceRoleAuthMiddleware());
