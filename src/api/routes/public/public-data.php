<?php

use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/data', function (RouteCollectorProxy $group): void {
    $group->get('/countries', 'getCountries');
    $group->get('/countries/', 'getCountries');
    $group->get('/countries/{countryCode}/states', 'getStates');
    $group->get('/countries/{countryCode}/states/', 'getStates');
});

function getCountries(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, array_values(Countries::getAll()));
}

function getStates(Request $request, Response $response, array $args): Response
{
    $states = new States($args['countryCode']);

    return SlimUtils::renderJSON($response, $states->getAll());
}
