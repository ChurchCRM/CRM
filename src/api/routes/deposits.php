<?php
// Routes
use ChurchCRM\DepositQuery;
use ChurchCRM\Deposit;

$app->group('/deposits', function () {

  $this->post('', function ($request, $response, $args) {
    $input = (object)$request->getParsedBody();
    $deposit = new Deposit();
    $deposit->setType($input->depositType);
    $deposit->setComment($input->depositComment);
    $deposit->setDate($input->depositDate);
    $deposit->save();
    echo $deposit->toJSON();
  });

  $this->get('', function ($request, $response, $args) {
    echo DepositQuery::create()->find()->toJSON();
  });

  $this->get('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    echo DepositQuery::create()->findOneById($id)->toJSON();
  });

  $this->post('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $input = (object)$request->getParsedBody();
    $thisDeposit = DepositQuery::create()->findOneById($id);
    $thisDeposit->setType($input->depositType);
    $thisDeposit->setComment($input->depositComment);
    $thisDeposit->setDate($input->depositDate);
    $thisDeposit->setClosed($input->depositClosed);
    $thisDeposit->save();
    echo $thisDeposit->toJSON();
  });


  $this->get('/{id:[0-9]+}/ofx', function ($request, $response, $args) {
    $id = $args['id'];
    $OFX = DepositQuery::create()->findOneById($id)->getOFX();
    header($OFX->header);
    echo $OFX->content;
  });

  $this->get('/{id:[0-9]+}/pdf', function ($request, $response, $args) {
    $id = $args['id'];
    DepositQuery::create()->findOneById($id)->getPDF();
  });

  $this->get('/{id:[0-9]+}/csv', function ($request, $response, $args) {
    $id = $args['id'];
    //echo DepositQuery::create()->findOneById($id)->toCSV();
    header("Content-Disposition: attachment; filename=ChurchCRM-Deposit-" . $id . "-" . date("Ymd-Gis") . ".csv");
    echo ChurchCRM\PledgeQuery::create()->filterByDepid($id)
            ->joinDonationFund()->useDonationFundQuery()
            ->withColumn("DonationFund.Name", "DonationFundName")
            ->endUse()
            ->joinFamily()->useFamilyQuery()
            ->withColumn("Family.Name","FamilyName")
            ->endUse()
            ->find()
            ->toCSV();
  });

  $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    DepositQuery::create()->findOneById($id)->delete();
    echo json_encode(["success" => true]);
  });

  $this->get('/{id:[0-9]+}/pledges', function ($request, $response, $args) {
    $id = $args['id'];
    echo \ChurchCRM\PledgeQuery::create()
            ->filterByDepid($id)
            ->groupByGroupkey()
            ->withColumn("SUM(Pledge.Amount)","sumAmount")
            ->joinFamily(null, Propel\Runtime\ActiveQuery\Criteria::LEFT_JOIN)
            ->withColumn("Family.Name")
            ->joinDonationFund()
            ->withColumn("DonationFund.Name")
            ->find()
            ->toJSON();
  });
  
});
