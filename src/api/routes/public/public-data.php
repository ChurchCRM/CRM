<?php

use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/data', function (RouteCollectorProxy $group): void {
    $group->get('/countries', 'getCountries');
    $group->get('/countries/', 'getCountries');
    $group->get('/countries/{countryCode}/states', 'getStates');
    $group->get('/countries/{countryCode}/states/', 'getStates');
});

/**
 * @OA\Get(
 *     path="/public/data/countries",
 *     operationId="getCountries",
 *     summary="List all countries",
 *     description="Returns the full list of countries used in address fields.",
 *     tags={"Lookups"},
 *     @OA\Response(
 *         response=200,
 *         description="Array of country objects",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="code", type="string", example="US"),
 *                 @OA\Property(property="name", type="string", example="United States")
 *             )
 *         )
 *     )
 * )
 */
function getCountries(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, array_values(Countries::getAll()));
}

/**
 * @OA\Get(
 *     path="/public/data/countries/{countryCode}/states",
 *     operationId="getStates",
 *     summary="List states/provinces for a country",
 *     description="Returns all states or provinces for the given ISO country code.",
 *     tags={"Lookups"},
 *     @OA\Parameter(
 *         name="countryCode",
 *         in="path",
 *         required=true,
 *         description="ISO 3166-1 alpha-2 country code",
 *         @OA\Schema(type="string", example="US")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of state/province objects",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="code", type="string", example="WA"),
 *                 @OA\Property(property="name", type="string", example="Washington")
 *             )
 *         )
 *     )
 * )
 */
function getStates(Request $request, Response $response, array $args): Response
{
    $states = new States($args['countryCode']);

    return SlimUtils::renderJSON($response, $states->getAll());
}
