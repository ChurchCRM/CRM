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

  try {
    array_push($resultsArray, $this->FamilyService->getFamiliesJSON($this->FamilyService->search($query)));
  } catch (Exception $e) {
  }

  try {
    array_push($resultsArray, $this->GroupService->getGroupJSON($this->GroupService->search($query)));
  } catch (Exception $e) {
  }

  try {
    array_push($resultsArray, $this->FinancialService->getDepositJSON($this->FinancialService->searchDeposits($query)));
  } catch (Exception $e) {
  }

  try {
    array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
  } catch (Exception $e) {
  }

  $data = ["results" => array_filter($resultsArray)];

  return $response->withJson($data);
});
