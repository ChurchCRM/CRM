<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Search\AddressSearchResultProvider;
use ChurchCRM\Search\BaseSearchResultProvider;
use ChurchCRM\Search\CalendarEventSearchResultProvider;
use ChurchCRM\Search\FamilySearchResultProvider;
use ChurchCRM\Search\FinanceDepositSearchResultProvider;
use ChurchCRM\Search\FinancePaymentSearchResultProvider;
use ChurchCRM\Search\GroupSearchResultProvider;
use ChurchCRM\Search\PersonSearchResultProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/search', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer('templates/search/');

    $query  = trim($request->getQueryParams()['q'] ?? '');
    $groups = [];

    if (mb_strlen($query) >= 2) {
        /** @var BaseSearchResultProvider[] $providers */
        $providers = [
            new PersonSearchResultProvider(),
            new AddressSearchResultProvider(),
            new FamilySearchResultProvider(),
            new GroupSearchResultProvider(),
            new FinanceDepositSearchResultProvider(),
            new FinancePaymentSearchResultProvider(),
            new CalendarEventSearchResultProvider(),
        ];

        foreach ($providers as $provider) {
            $result = $provider->getSearchResults($query);
            if (count($result->results) > 0) {
                $groups[] = $result;
            }
        }
    }

    $totalResults = (int) array_sum(array_map(fn ($g) => count($g->results), $groups));

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Search Results'),
        'query'        => $query,
        'groups'       => $groups,
        'totalResults' => $totalResults,
    ];

    return $renderer->render($response, 'search-results.php', $pageArgs);
});
