<?php
use ChurchCRM\DepositQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use Propel\Runtime\ActiveQuery\Criteria;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $resultsArray = [];

    //Person Search
    try {
        array_push($resultsArray, $this->PersonService->getPersonsJSON($this->PersonService->search($query)));
    } catch (Exception $e) {
        $this->Logger->warn($e->getMessage());
    }

    //family search
    try {
      $results = [];
      $q = FamilyQuery::create()
          ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
          ->limit(15)
          ->find();
      foreach ($q as $family)
      {
        array_push($results,$family->toSearchArray());
      }
      array_push($resultsArray, json_encode(["families"=>$results]));
    } catch (Exception $e) {
        $this->Logger->warn($e->getMessage());
    }


    try {
        $q = GroupQuery::create()
            ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
            ->limit(15)
            ->withColumn('grp_Name', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/GroupView.php?GroupID=",Group.Id)', 'uri')
            ->select(['displayName', 'uri'])
            ->find();

        array_push($resultsArray, $q->toJSON());
    } catch (Exception $e) {
        $this->Logger->warn($e->getMessage());
    }

    //Deposits Search
    if ($_SESSION['bFinance']) {
        try {
            $q = DepositQuery::create();
            $q->filterByComment("%$query%", Criteria::LIKE)
                ->_or()
                ->filterById($query)
                ->_or()
                ->usePledgeQuery()
                ->filterByCheckno("%$query%", Criteria::LIKE)
                ->endUse()
                ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
                ->limit(5);
            array_push($resultsArray, $q->find()->toJSON());
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }

        //Search Payments
        try {
            array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }

    $data = ['results' => array_filter($resultsArray)];

    return $response->withJson($data);
});
