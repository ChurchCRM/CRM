<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/deposits', function (RouteCollectorProxy $group) {
    $group->post('', function (Request $request, Response $response, array $args) {
        $input = $request->getParsedBody();
        $deposit = new Deposit();
        $deposit->setType($input['depositType']);
        $deposit->setComment($input['depositComment']);
        $deposit->setDate($input['depositDate']);
        $deposit->save();
        return SlimUtils::renderJSON($response, $deposit->toArray());
    });

    $group->get('/dashboard', function (Request $request, Response $response, array $args) {
        $list = DepositQuery::create()
            ->filterByDate(['min' => date('Y-m-d', strtotime('-90 days'))])
            ->find();

        return SlimUtils::renderJSON($response, $list->toArray());
    });

    $group->get('', function (Request $request, Response $response, array $args) {
        return SlimUtils::renderStringJSON($response, DepositQuery::create()->find()->toJSON());
    });

    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        return SlimUtils::renderJSON($response, DepositQuery::create()->findOneById($id)->toArray());
    });

    $group->post('/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $input = $request->getParsedBody();
        $appDeposit = DepositQuery::create()->findOneById($id);
        $appDeposit->setType($input['depositType']);
        $appDeposit->setComment($input['depositComment']);
        $appDeposit->setDate($input['depositDate']);
        $appDeposit->setClosed($input['depositClosed']);
        $appDeposit->save();
        return SlimUtils::renderJSON($response, $appDeposit->toArray());
    });

    $group->get('/{id:[0-9]+}/ofx', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $OFX = DepositQuery::create()->findOneById($id)->getOFX();
        header($OFX->header);
        return SlimUtils::renderJSON($response, $OFX->content);
    });

    $group->get('/{id:[0-9]+}/pdf', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        DepositQuery::create()->findOneById($id)->getPDF();
    });

    $group->get('/{id:[0-9]+}/csv', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        //echo DepositQuery::create()->findOneById($id)->toCSV();
        header('Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $id . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv');
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

    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        DepositQuery::create()->findOneById($id)->delete();
        return SlimUtils::renderSuccessJSON($request);
    });

    $group->get('/{id:[0-9]+}/pledges', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $Pledges = PledgeQuery::create()
            ->filterByDepId($id)
            ->groupByGroupKey()
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->joinDonationFund()
            ->withColumn('DonationFund.Name')
            ->find()
            ->toArray();

        return SlimUtils::renderJSON($response, $Pledges);
    });
})->add(FinanceRoleAuthMiddleware::class);
