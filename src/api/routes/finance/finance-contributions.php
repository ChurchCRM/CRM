<?php


use ChurchCRM\DonationFundQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\TransactionQuery;

$app->group('/contrib', function () {
   
    // get list of all contributions
    $this->get('', function ($request, $response, $args) {
        echo TransactionQuery::create()
            ->JoinTransactionSplit()
            ->useTransactionSplitQuery()
                  ->JoinAccount()
                  ->useAccountQuery()
                    ->filterByAccountType("FUND")
                  ->endUse()
                ->endUse()
            ->withColumn('SUM(tran_split.tran_split_Amount)', 'totalAmount')
            ->groupBy("transaction_tran.tran_ID")
            ->find()
            ->toJSON();
    });
    // // get a single contribtion
    // $this->get('/{id:[0-9]+/contribution}', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     echo ContribQuery::create()
    //         ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
    //         // ->groupById()
    //         ->findOneById($id)
    //         ->toJSON();
    // });
    // // get a list of contribtion for a single person
    // $this->get('/{id:[0-9]+/person}', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     echo ContribQuery::create()
    //         ->filterByConId($id)
    //         // ->groupById()
    //         ->find()
    //         ->toJSON();
    // });

    // // get a list of contribtions associated with a deposit single
    // $this->get('/{id:[0-9]+/deposit}', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     echo ContribQuery::create()
    //         ->filterByDepId($id)
    //         ->groupById()
    //         ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
    //         ->find()
    //         ->toJSON();
    // });

    // // get a list of contribtions NOT associated with a deposit
    // $this->get('/deposit', function ($request, $response, $args) {
    //     //$id = $args['id'];
    //     echo ContribQuery::create()
    //         ->filterByDepId(null)
    //         ->groupById()
    //         ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
    //         ->find()
    //         ->toJSON();
    // });
    // $this->get('/{id:[0-9]+}/ofx', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     $OFX = ContribQuery::create()->findOneById($id)->getOFX();
    //     header($OFX->header);
    //     echo $OFX->content;
    // });

    // $this->get('/{id:[0-9]+}/pdf', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     ContribQuery::create()->findOneById($id)->getPDF();
    // });

    // $this->get('/{id:[0-9]+}/csv', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     //echo ContribQuery::create()->findOneById($id)->toCSV();
    //     header('Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $id . '-' . date(SystemConfig::getValue("sDateFilenameFormat")) . '.csv');
    //     // echo ChurchCRM\PledgeQuery::create()->filterByDepid($id)
    //     //     ->joinDonationFund()->useDonationFundQuery()
    //     //     ->withColumn('DonationFund.Name', 'DonationFundName')
    //     //     ->endUse()
    // //     //     ->joinFamily()->useFamilyQuery()
    // //     //     ->withColumn('Family.Name', 'FamilyName')
    // //     //     ->endUse()
    // //     //     ->find()
    // //     //     ->toCSV();
    // // });
    // // delete single contribution and associated splits
    // $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     ContribQuery::create()->findOneById($id)->delete();
    //     ContribSplitQuery::create()->filterByConId($id)->delete();
    //     echo json_encode(['success' => true]);
    // });
    // // $this->get('/{id:[0-9]+}/contrib', function ($request, $response, $args) {
    // //     $id = $args['id'];
    // //     $Contrib = \ChurchCRM\ContribQuery::create()
    // //         ->filterByConId($id)
    //     //     //->groupByGroupkey()
    //     //     ->groupByFamId()
    //     //     ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
    //     //     ->joinDonationFund()
    //     //     ->withColumn('DonationFund.Name')
    //         ->find()
    //         ->toArray()
    //     ;
    //     return $response->withJSON($Contrib);

    // });
})->add(new FinanceRoleAuthMiddleware());

// $app->group('/split', function () {
//     // get list of splits for a contribtion
//     $this->get('/{id:[0-9]+}/splits', function ($request, $response, $args) {
//         $ConID = $args['id'];
//         echo ContribSplitQuery::create()
//             ->leftJoinDonationFund()
//             ->withColumn('fun_Name')
//             ->filterByConId($ConID)
//             ->find()
//             ->toJSON();
//     });
//     // delete single split
//     $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
//         $id = $args['id'];
//         ContribSplitQuery::create()->findOneById($id)->delete();
//         echo json_encode(['success' => true]);
//     });
//     // add new contribution split
//     $this->post('', function ($request, $response, $args) {
//         $input = (object)$request->getParsedBody();
//         // echo print_r($input);
//         $contribution = new ContribSplit();
//         $contribution->setConId($input->AddConId);
//         $contribution->setFundId($input->AddFund);
//         $contribution->setAmount($input->AddAmount);
//         $contribution->setComment($input->AddComment);
//         $contribution->setNondeductible($input->AddNonDeductible);
//         $contribution->save();
//         echo $contribution->toJSON();
//     });
// })->add(new FinanceRoleAuthMiddleware());

// $app->group('/activefunds', function () {
//     // get list of funds
//     $this->get('', function ($request, $response, $args) {
//         $ConID = $args['id'];
//         echo DonationFundQuery::create()
//             ->filterByActive('true')
//             ->find()
//             ->toJSON();
//     });
// })->add(new FinanceRoleAuthMiddleware());