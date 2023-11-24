<?php

use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\GeoUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/geocoder', function (RouteCollectorProxy $group) {
    $group->post('/address', 'getGeoLocals');
    $group->post('/address/', 'getGeoLocals');
});

function getGeoLocals(Request $request, Response $response, array $p_args)
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    if (!empty($input)) {
        return SlimUtils::renderJSON($response, GeoUtils::getLatLong($input->address));
    }

    return $response->withStatus(400); // bad request
}
