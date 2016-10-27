<?php
// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments 
$app->get('/search/{query}', function ($request, $response, $args) {
  $query = $args['query'];
  $resultsArray = array();

  try {
    array_push($resultsArray, $this->PersonService->getPersonsJSON($this->PersonService->search($query)));
  } catch (Exception $e) {
  }
  
  if ( $_SESSION['bFinance'] )
  {
    try{
      $q = ChurchCRM\FamilyQuery::create()
        ->filterByEnvelope($query)
        ->limit(5)
        ->withColumn("fam_Name","displayName")
        ->withColumn('CONCAT("' . $sRootPath . 'FamilyView.php?FamilyID=",Family.Id)', "uri")
        ->select(array("displayName","uri"))
        ->find();
      array_push($resultsArray, str_replace('Families', 'Donation Envelopes', $q->toJSON()));
    } catch (Exception $ex) {
    }

    try {
      array_push($resultsArray, $this->FamilyService->getFamiliesJSON($this->FamilyService->search($query)));
    } catch (Exception $e) {
    }
  }

  try {
    array_push($resultsArray, $this->GroupService->getGroupJSON($this->GroupService->search($query)));
  } catch (Exception $e) {
  }

  try {
    $q= \ChurchCRM\DepositQuery::create();
    $q ->filterByComment("%$query%",  Propel\Runtime\ActiveQuery\Criteria::LIKE) 
         ->_or()
         ->filterById($query)
        ->_or()
        ->usePledgeQuery()
          ->filterByCheckno("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
        ->endUse()
        ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', "displayName")
        ->withColumn('CONCAT("' . $sRootPath . 'DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', "uri")
        ->limit(5);
    array_push($resultsArray, $q->find()->toJSON());
  } catch (Exception $e) {
  }

  try {
    array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
  } catch (Exception $e) {
  }

  $data = ["results" => array_filter($resultsArray)];

  return $response->withJson($data);
});
