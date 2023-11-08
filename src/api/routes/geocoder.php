<?php

use ChurchCRM\Utils\GeoUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/geocoder', function () use ($app) {
    $app->post('/address', 'getGeoLocals');
    $app->post('/address/', 'getGeoLocals');
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
        return $response->withJson(GeoUtils::getLatLong($input->address));
    }

    return $response->withStatus(400); // bad request
}
