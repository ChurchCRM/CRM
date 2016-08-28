<?php
// Routes


$app->group('/deposits', function () {

  $this->post('', function ($request, $response, $args) {
    $input = (object)$request->getParsedBody();
    $deposit = new \ChurchCRM\Deposit();
    $deposit->setType($input->depositType);
    $deposit->setComment($input->depositComment);
    $deposit->setDate($input->depositDate);
    $deposit->save();
    echo $deposit->toJSON();
  });

  $this->get('', function ($request, $response, $args) {
    echo \ChurchCRM\Base\DepositQuery::create()->find()->toJSON();
  });

  $this->get('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    echo \ChurchCRM\Base\DepositQuery::create()->findOneById($id)->toJSON();
  });

  $this->post('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $input = (object)$request->getParsedBody();
    $thisDeposit = \ChurchCRM\DepositQuery::create()->findOneById($id);
    $thisDeposit->setType($input->depositType);
    $thisDeposit->setComment($input->depositComment);
    $thisDeposit->setDate($input->depositDate);
    $thisDeposit->setClosed($input->depositClosed);
    $thisDeposit->save();
    echo $thisDeposit->toJSON();
  });


  $this->get('/{id:[0-9]+}/ofx', function ($request, $response, $args) {
    $id = $args['id'];
    $OFX = \ChurchCRM\Base\DepositQuery::create()->findOneById($id)->getOFX();
    header($OFX->header);
    echo $OFX->content;
  });

  $this->get('/{id:[0-9]+}/pdf', function ($request, $response, $args) {
    $id = $args['id'];
    $this->FinancialService->getDepositPDF($id);
  });

  $this->get('/{id:[0-9]+}/csv', function ($request, $response, $args) {
    $id = $args['id'];
    echo \ChurchCRM\Base\DepositQuery::create()->findOneById($id)->toCSV();
  });

  $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    \ChurchCRM\Base\DepositQuery::create()->findOneById($id)->delete();
    echo json_encode(["success" => true]);
  });

  $this->get('/{id:[0-9]+}/pledges', function ($request, $response, $args) {
    $id = $args['id'];
    echo \ChurchCRM\Base\DepositQuery::create()->findOneById($id)->getPledgesJoinAll()->toJSON();
  });
  
});
