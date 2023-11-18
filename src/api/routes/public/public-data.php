<?php

use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/public/data', function () use ($app) {
    $app->get('/countries', 'getCountries');
    $app->get('/countries/', 'getCountries');
    $app->get('/countries/{countryCode}/states', 'getStates');
    $app->get('/countries/{countryCode}/states/', 'getStates');
});

function getCountries(Request $request, Response $response, array $args)
{
    return $response->withJson(array_values(Countries::getAll()));
}

function getStates(Request $request, Response $response, array $args)
{
    $states = new States($args['countryCode']);

    return $response->withJson($states->getAll());
}
