<?php

use ChurchCRM\Utils\GeoUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/geocoder', function (RouteCollectorProxy $group) {
    $group->post('/address', 'getGeoLocals');
    $group->post('/address/', 'getGeoLocals');
});

/**
 * A method that return GeoLocation based on an address.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function getGeoLocals(Request $request, Response $response, array $p_args)
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    if (!empty($input)) {
        $response->getBody()->write(json_encode(GeoUtils::getLatLong($input->address)));

        return $response->withHeader('Content-Type', 'application/json');
    }

    return $response->withStatus(400); // bad request
}
