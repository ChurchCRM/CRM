<?php
// Routes

$app->group('/payments', function () {
  $this->get('/', function ($request, $response, $args) {
    $this->FinancialService->getPaymentJSON($this->FinancialService->getPayments());
  });

  $this->post('/', function ($request, $response, $args) {
    $payment = $request->getParsedBody();
    echo json_encode(["payment" => $this->FinancialService->submitPledgeOrPayment($payment)]);
  });

  $this->delete('/{groupKey}', function ($request, $response, $args) {
    $groupKey = $args['groupKey'];
    $this->FinancialService->deletePayment($groupKey);
    echo json_encode(["status" => "ok"]);
  });
});

