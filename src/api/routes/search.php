<?php
/*******************************************************************************
 *
 *  filename    : api/routes/search.php
 *  last change : 2017/10/29 Philippe Logel
 *  description : Search terms like : Firstname, Lastname, phone, address,
 *                                 groups, families, etc...
 *
 ******************************************************************************/

use ChurchCRM\Search\AddressSearchResultProvider;
use ChurchCRM\Search\FamilySearchResultProvider;
use ChurchCRM\Search\FinanceDepositSearchResultProvider;
use ChurchCRM\Search\FinancePaymentSearchResultProvider;
use ChurchCRM\Search\PersonSearchResultProvider;
use ChurchCRM\Search\GroupSearchResultProvider;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $resultsArray = [];

    array_push($resultsArray,PersonSearchResultProvider::getSearchResults($query));
    array_push($resultsArray,AddressSearchResultProvider::getSearchResults($query));
    array_push($resultsArray,FamilySearchResultProvider::getSearchResults($query));
    array_push($resultsArray,GroupSearchResultProvider::getSearchResults($query));
    array_push($resultsArray,FinanceDepositSearchResultProvider::getSearchResults($query));
    array_push($resultsArray,FinancePaymentSearchResultProvider::getSearchResults($query));
    return $response->withJson(array_values(array_filter($resultsArray)));
});
