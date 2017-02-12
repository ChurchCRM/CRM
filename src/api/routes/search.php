<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyQuery;
use ChurchCRM\DepositQuery;
use ChurchCRM\PledgeQuery;
// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $resultsArray = [];

    try {
        array_push($resultsArray, $this->PersonService->getPersonsJSON($this->PersonService->search($query)));
    } catch (Exception $e) {
    }

    if ($_SESSION['bFinance']) {
        try {
            $q = FamilyQuery::create()
        ->filterByEnvelope($query)
        ->limit(5)
        ->withColumn('fam_Name', 'displayName')
        ->withColumn('CONCAT("'.SystemURLs::getRootPath().'FamilyView.php?FamilyID=",Family.Id)', 'uri')
        ->select(['displayName', 'uri'])
        ->find();
            array_push($resultsArray, str_replace('Families', 'Donation Envelopes', $q->toJSON()));
        } catch (Exception $ex) {
        }
    }

    try {
      $q = FamilyQuery::create()
        ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
        ->limit(15)
        ->withColumn('fam_Name', 'displayName')
        ->withColumn('CONCAT("'.SystemURLs::getRootPath().'FamilyView.php?FamilyID=",Family.Id)', 'uri')
        ->select(['displayName', 'uri'])
        ->find();

      array_push($resultsArray, $q->toJSON());
    } catch (Exception $e) {
    }

    try {
        array_push($resultsArray, $this->GroupService->getGroupJSON($this->GroupService->search($query)));
    } catch (Exception $e) {
    }

    try {
        $q = DepositQuery::create();
        $q->filterByComment("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
         ->_or()
         ->filterById($query)
        ->_or()
        ->usePledgeQuery()
          ->filterByCheckno("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
        ->endUse()
        ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
        ->withColumn('CONCAT("'.SystemURLs::getRootPath().'DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
        ->limit(5);
        array_push($resultsArray, $q->find()->toJSON());
    } catch (Exception $e) {
    }

    if ($_SESSION['bFinance']) {
      try {
        $q = DepositQuery::create()
          ->joinPledge()
          ->usePledgeQuery()
            ->filterByCheckno("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
            ->joinFamily()
          ->endUse()
          ->limit(15)
          ->withColumn('CONCAT("Check #",Pledge.Checkno," ",Family.Name," ",Deposit.Date)', 'displayName')
          ->withColumn('CONCAT("'.SystemURLs::getRootPath().'DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
          ->find();

          array_push($resultsArray, str_replace('Deposits', 'Pledges',$q->toJSON()));
      } catch (Exception $e) {
        echo $e;
        exit;
      }
    }
    $data = ['results' => array_filter($resultsArray)];

    return $response->withJson($data);
});
