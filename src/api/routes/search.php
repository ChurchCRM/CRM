<?php

use ChurchCRM\Search\AddressSearchResultProvider;
use ChurchCRM\Search\CalendarEventSearchResultProvider;
use ChurchCRM\Search\FamilySearchResultProvider;
use ChurchCRM\Search\FinanceDepositSearchResultProvider;
use ChurchCRM\Search\FinancePaymentSearchResultProvider;
use ChurchCRM\Search\GroupSearchResultProvider;
use ChurchCRM\Search\iSearchResultProvider;
use ChurchCRM\Search\PersonSearchResultProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function (Request $request, Response $response, array $args) {
    $query = $args['query'];
    $resultsArray = [];
    $resultsProviders = [
        new PersonSearchResultProvider(),
        new AddressSearchResultProvider(),
        new FamilySearchResultProvider(),
        new GroupSearchResultProvider(),
        new FinanceDepositSearchResultProvider(),
        new FinancePaymentSearchResultProvider(),
        new CalendarEventSearchResultProvider(),
    ];

    foreach ($resultsProviders as $provider) {
        /* @var $provider iSearchResultProvider */
        array_push($resultsArray, $provider->getSearchResults($query));
    }

    return $response->withJson(array_values(array_filter($resultsArray)));
});
