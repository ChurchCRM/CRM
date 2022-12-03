<?php

use ChurchCRM\Deposit;
use ChurchCRM\DepositQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

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

    $this->get('/dashboard', function ($request, $response, $args) {
        $list = DepositQuery::create()
            ->filterByDate(['min' =>date('Y-m-d', strtotime('-90 days'))])
            ->find();
        return $response->withJson($list->toArray());
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
        header('Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $id . '-' . date(SystemConfig::getValue("sDateFilenameFormat")) . '.csv');
        echo PledgeQuery::create()->filterByDepId($id)
            ->joinDonationFund()->useDonationFundQuery()
            ->withColumn('DonationFund.Name', 'DonationFundName')
            ->endUse()
            ->joinFamily()->useFamilyQuery()
            ->withColumn('Family.Name', 'FamilyName')
            ->endUse()
            ->find()
            ->toCSV();
    });

    $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
        $id = $args['id'];
        DepositQuery::create()->findOneById($id)->delete();
        echo json_encode(['success' => true]);
    });

    $this->get('/{id:[0-9]+}/pledges', function ($request, $response, $args) {
        $id = $args['id'];
        $Pledges = PledgeQuery::create()
            ->filterByDepId($id)
            ->groupByGroupKey()
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->joinDonationFund()
            ->withColumn('DonationFund.Name')
            ->find()
            ->toArray();
        return $response->withJson($Pledges);

    });
})->add(new FinanceRoleAuthMiddleware());
