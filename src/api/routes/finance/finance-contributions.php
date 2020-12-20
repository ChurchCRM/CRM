<?php

use ChurchCRM\Contrib;
use ChurchCRM\ContribSplit;
use ChurchCRM\ContribQuery;
use ChurchCRM\ContribSplitQuery;
use ChurchCRM\DonationFundQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;


$app->group('/contrib', function () {
    // add new contribution
    $this->post('', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $contribution = new Contrib();
        $contribution->setConId($input->AddContributorId);
        $contribution->setTypeOfMbr($input->AddTypeOfMbr);
        $contribution->setDate($input->AddDate);
        $contribution->setComment($input->AddComment);
        $contribution->setDateentered($input->AddDateEntered);
        $contribution->setEnteredby($input->AddEnteredBy);
        $contribution->save();
        echo $contribution->toJSON();
    });
    // update contribution
    $this->post('/{id:[0-9]+}', function ($request, $response, $args) {
        $id = $args['id'];
        $input = (object)$request->getParsedBody();
        $contribution = ContribQuery::create()->findOneById($id);
        $contribution->setConId($input->ContributorId);
        $contribution->setTypeOfMbr($input->TypeOfMbr);
        $contribution->setDate($input->Date);
        $contribution->setComment($input->Comment);
        $contribution->setDatelastedited($input->DateLastEdited);
        $contribution->setEditedby($input->EnteredBy);
        $contribution->save();
        echo $contribution->toJSON();
    });
    // add/remove contribution to/from deposit
    $this->post('/{id:[0-9]+}/deposit', function ($request, $response, $args) {
        $id = $args['id'];
        $input = (object)$request->getParsedBody();
        $contribution = ContribQuery::create()->findOneById($id);
        $contribution->setDepId($input->DepId);
        $contribution->save();
        echo $contribution->toJSON();
    });

    // get list of all contributions
    $this->get('', function ($request, $response, $args) {
        echo ContribQuery::create()
            ->groupById()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
            ->leftJoinPerson()
            ->withColumn("per_ID", "per_ID")
            ->withColumn("per_FirstName", "FirstName")
            ->withColumn("per_LastName", "LastName")
            ->withColumn("per_Envelope", "Envelope")
            ->leftJoinContribSplit()
            ->withColumn("spl_FundId", "spl_FundId")
            ->find()
            ->toJSON();
    });
    // get a single contribtion
    $this->get('/{id:[0-9]+/contribution}', function ($request, $response, $args) {
        $id = $args['id'];
        echo ContribQuery::create()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
            // ->leftJoinPerson()
            // ->withColumn("per_ID", "per_ID")
            // ->withColumn("per_FirstName", "FirstName")
            // ->withColumn("per_LastName", "LastName")
            // ->withColumn("per_Envelope", "Envelope")
            ->leftJoinContribSplit()
            ->withColumn("spl_FundId", "spl_FundId")
            ->findOneById($id)
            ->toJSON();
    });
    // get a list of contribtion for a single person
    $this->get('/{id:[0-9]+/person}', function ($request, $response, $args) {
        $id = $args['id'];
        echo ContribQuery::create()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
            // ->leftJoinPerson()
            // ->withColumn("per_ID", "per_ID")
            // ->withColumn("per_FirstName", "FirstName")
            // ->withColumn("per_LastName", "LastName")
            // ->withColumn("per_Envelope", "Envelope")
            ->leftJoinContribSplit()
            // ->withColumn("spl_FundId", "spl_FundId")
            ->filterByConId($id)
            ->groupById()
            ->find()
            ->toJSON();
            
    });

    // get a list of contribtions associated with a deposit single 
    $this->get('/{id:[0-9]+/deposit}', function ($request, $response, $args) {
        $id = $args['id'];
        echo ContribQuery::create()            
            ->leftJoinPerson()
            // ->withColumn("per_ID", "per_ID")
            ->withColumn("per_FirstName", "FirstName")
            ->withColumn("per_LastName", "LastName")
            // ->withColumn("per_Envelope", "Envelope")
            ->leftJoinContribSplit()
            // ->withColumn("spl_FundId", "spl_FundId")
            ->filterByDepId($id)
            ->groupById()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
            ->find()
            ->toJSON();
    });

    // get a list of contribtions NOT associated with a deposit
    $this->get('/deposit', function ($request, $response, $args) {
        //$id = $args['id'];
        echo ContribQuery::create()            
            ->leftJoinPerson()
            ->withColumn("per_ID", "per_ID")
            ->withColumn("per_FirstName", "FirstName")
            ->withColumn("per_LastName", "LastName")
            ->withColumn("per_Envelope", "Envelope")
            ->leftJoinContribSplit()
            // ->withColumn("spl_FundId", "spl_FundId")
            ->filterByDepId(null)
            ->groupById()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
            ->find()
            ->toJSON();
    });
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

    $this->get('/{id:[0-9]+}/csv', function ($request, $response, $args) {
        $id = $args['id'];
        // fix
        header('Content-Disposition: attachment; filename=ChurchCRM-Contribution-' . $id . '-' . date(SystemConfig::getValue("sDateFilenameFormat")) . '.csv');
        echo ContribQuery::create()
        ->select(['con_ID','con_Date', 'con_Comment', 'con_Method', 'con_CheckNo'])
        ->usePersonQuery()
            ->withColumn("per_FirstName", "FirstName")
            ->withColumn("per_LastName", "LastName")
            ->withColumn("per_Envelope", "Envelope")
        ->endUse()
        ->useContribSplitQuery()
            ->withColumn('SUM(contrib_split.spl_Amount)', 'Total')
        ->endUse()
        ->filterById($id)
        ->find()
        ->toCSV();
    });
    // delete single contribution and associated splits
    $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
        $id = $args['id'];
        ContribQuery::create()->findOneById($id)->delete();
        ContribSplitQuery::create()->filterByConId($id)->delete();
        echo json_encode(['success' => true]);
    });
    // $this->get('/{id:[0-9]+}/contrib', function ($request, $response, $args) {
    //     $id = $args['id'];
    //     $Contrib = \ChurchCRM\ContribQuery::create()
    //         ->filterByConId($id)
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

$app->group('/split', function () {
    // get list of splits for a contribtion
    $this->get('/{id:[0-9]+}/splits', function ($request, $response, $args) {
        $ConID = $args['id'];
        echo ContribSplitQuery::create()
            ->leftJoinDonationFund()
            ->withColumn('fun_Name')
            ->filterByConId($ConID)
            ->find()
            ->toJSON();
    });
    // get single split, used to edit single split
    $this->post('/{id:[0-9]+}/split', function ($request, $response, $args) {
        $id = $args['id'];
        $input = (object)$request->getParsedBody();
        $contribution = ContribSplitQuery::create()->findOneById($id);
        $contribution->setFundId($input->EditFund);
        $contribution->setAmount($input->EditAmount);
        $contribution->setComment($input->EditComment);
        $contribution->setNondeductible($input->EditNonDeductible);
        $contribution->save();
        echo $contribution->toJSON();
    });
    // delete single split
    $this->delete('/{id:[0-9]+}', function ($request, $response, $args) {
        $id = $args['id'];
        ContribSplitQuery::create()->findOneById($id)->delete();
        echo json_encode(['success' => true]);
    });
    // add new contribution split
    $this->post('', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        // echo print_r($input);
        $contribution = new ContribSplit();
        $contribution->setConId($input->AddConId);
        $contribution->setFundId($input->AddFund);
        $contribution->setAmount($input->AddAmount);
        $contribution->setComment($input->AddComment);
        $contribution->setNondeductible($input->AddNonDeductible);
        $contribution->save();
        echo $contribution->toJSON();
    });
})->add(new FinanceRoleAuthMiddleware());

$app->group('/activefunds', function () {
    // get list of funds
    $this->get('', function ($request, $response, $args) {
        $ConID = $args['id'];
        echo DonationFundQuery::create()
            ->filterByActive('true')
            ->find()
            ->toJSON();
    });
})->add(new FinanceRoleAuthMiddleware());