<?php
// Routes


$app->group('/deposits', function () use ($app) {

  $app->post('/', function () use ($app) {

    $input = getJSONFromApp($app);
    echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate));
  });

  $app->get('/', function () use ($app) {

    echo json_encode(["deposits" => $app->FinancialService->getDeposits()]);
  });

  $app->get('/:id', function ($id) use ($app) {

    echo json_encode(["deposits" => $app->FinancialService->getDeposits($id)]);
  })->conditions(array('id' => '[0-9]+'));

  $app->post('/:id', function ($id) use ($app) {

    $input = getJSONFromApp($app);
    echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate, $id, $input->depositClosed));
  });


  $app->get('/:id/ofx', function ($id) use ($app) {

    $OFX = $app->FinancialService->getDepositOFX($id);
    header($OFX->header);
    echo $OFX->content;
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/pdf', function ($id) use ($app) {
    $app->contentType("application/x-download");
    $app->FinancialService->getDepositPDF($id);
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/csv', function ($id) use ($app) {

    $CSV = $app->FinancialService->getDepositCSV($id);
    header($CSV->header);
    echo $CSV->content;
  })->conditions(array('id' => '[0-9]+'));

  $app->delete('/:id', function ($id) use ($app) {

    $app->FinancialService->deleteDeposit($id);
    echo json_encode(["success" => true]);
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/payments', function ($id) use ($app) {

    echo $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments($id));
  })->conditions(array('id' => '[0-9]+'));
});
