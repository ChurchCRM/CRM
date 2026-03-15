<?php

use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\GeoUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/geocoder', function (RouteCollectorProxy $group): void {
    $group->post('/address', 'getGeoLocals');
    $group->post('/address/', 'getGeoLocals');
});

/**
 * @OA\Post(
 *     path="/geocoder/address",
 *     summary="Geocode an address — returns latitude and longitude",
 *     tags={"Map"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(@OA\Property(property="address", type="string", description="Full address string to geocode"))
 *     ),
 *     @OA\Response(response=200, description="Latitude and longitude for the provided address",
 *         @OA\JsonContent(
 *             @OA\Property(property="Latitude", type="number", format="float"),
 *             @OA\Property(property="Longitude", type="number", format="float")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Empty or invalid request body")
 * )
 */
function getGeoLocals(Request $request, Response $response, array $p_args): Response
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    if (empty($input)) {
        throw new HttpBadRequestException($request);
    }

    return SlimUtils::renderJSON($response, GeoUtils::getLatLong($input->address));
}
