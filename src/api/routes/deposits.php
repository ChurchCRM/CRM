<?php
// Routes


$app->group('/deposits', function () {

  $this->post('/', function ($request, $response, $args) {
    $input = $request->getParsedBody();
    echo json_encode($this->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate));
  });

  $this->get('/', function ($request, $response, $args) {
    echo json_encode(["deposits" => $this->FinancialService->getDeposits()]);
  });

  $this->get('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    echo json_encode(["deposits" => $this->FinancialService->getDeposits($id)]);
  });

  $this->post('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $input = $request->getParsedBody();
    echo json_encode($this->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate, $id, $input->depositClosed));
  });


  $this->get('/{id:[0-9]+}/ofx', function ($request, $response, $args) {
    $id = $args['id'];
    $OFX = $this->FinancialService->getDepositOFX($id);
    header($OFX->header);
    echo $OFX->content;
  });

  $this->get('/{id:[0-9]+}/pdf', function ($request, $response, $args) {
    $id = $args['id'];
    $this->FinancialService->getDepositPDF($id);
  });

  $this->get('/{id:[0-9]+}/csv', function ($request, $response, $args) {
    $id = $args['id'];
    $CSV = $this->FinancialService->getDepositCSV($id);
    header($CSV->header);
    echo $CSV->content;
  });

  $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $this->FinancialService->deleteDeposit($id);
    echo json_encode(["success" => true]);
  });

  $this->get('/{id:[0-9]+}/payments', function ($request, $response, $args) {
    $id = $args['id'];
    echo $this->FinancialService->getPaymentJSON($this->FinancialService->getPayments($id));
  });
});
