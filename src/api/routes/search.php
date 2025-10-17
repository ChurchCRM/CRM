<?php

use ChurchCRM\Search\AddressSearchResultProvider;
use ChurchCRM\Search\BaseSearchResultProvider;
use ChurchCRM\Search\CalendarEventSearchResultProvider;
use ChurchCRM\Search\FamilySearchResultProvider;
use ChurchCRM\Search\FinanceDepositSearchResultProvider;
use ChurchCRM\Search\FinancePaymentSearchResultProvider;
use ChurchCRM\Search\GroupSearchResultProvider;
use ChurchCRM\Search\PersonSearchResultProvider;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
    $query = $args['query'];
    $resultsArray = [];

    /* @var BaseSearchResultProvider[] $resultsProviders */
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
        $searchResult = $provider->getSearchResults($query);
        if (count($searchResult->results) > 0) {
            $resultsArray[] = $searchResult;
        }
    }

    return SlimUtils::renderJSON($response, array_values(array_filter($resultsArray)));
});
