<?php
// Routes


$app->group('/payments', function () use ($app) {
  $app->get('/', function () use ($app) {

    $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments());
  });
  $app->post('/', function () use ($app) {

    $payment = getJSONFromApp($app);
    echo json_encode(["payment" => $app->FinancialService->submitPledgeOrPayment($payment)]);
  });
  $app->get('/:id', function ($id) use ($app) {

//$payment = getJSONFromApp($app);
//echo $app->FinancialService->getDepositsByFamilyID($fid); //This might not work yet...
    echo json_encode(["status" => "Not Implemented"]);
  });
  $app->get('/byFamily/:familyId(/:fyid)', function ($familyId, $fyid = -1) use ($app) {

    echo '{"status":"Not implemented"}';
//$payment = getJSONFromApp($app);
#$app->FinancialService->getDepositsByFamilyID($fid);//This might not work yet...
  });
  $app->delete('/:groupKey', function ($groupKey) use ($app) {

    $app->FinancialService->deletePayment($groupKey);
    echo json_encode(["status" => "ok"]);
  });
});

