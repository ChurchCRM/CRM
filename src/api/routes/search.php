<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use Propel\Runtime\ActiveQuery\Criteria;
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
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . 'FamilyView.php?FamilyID=",Family.Id)', 'uri')
                ->select(['displayName', 'uri'])
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
        $dbGroups = GroupQuery::create()
            ->filterByName("%$query%", Criteria::LIKE)
            ->_or()
            ->filterByDescription("%$query%", Criteria::LIKE)
            ->orderByName()
            ->withColumn('grp_Name', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . 'GroupView.php?GroupID=",grp_Id)', 'uri')
            ->find();
        array_push($resultsArray, $dbGroups->toJSON());
    } catch (Exception $e) {
    }

    try {
        $q = \ChurchCRM\DepositQuery::create();
        $q->filterByComment("%$query%", Criteria::LIKE)
            ->_or()
            ->filterById($query)
            ->_or()
            ->usePledgeQuery()
            ->filterByCheckno("%$query%", Criteria::LIKE)
            ->endUse()
            ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . 'DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
            ->limit(5);
        array_push($resultsArray, $q->find()->toJSON());
    } catch (Exception $e) {
    }

    try {
        array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
    } catch (Exception $e) {
    }

    $data = ['results' => array_filter($resultsArray)];

    return $response->withJson($data);
});
