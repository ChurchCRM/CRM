<?php

use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/properties', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Delete(
     *     path="/admin/api/properties/definition/{propertyId}",
     *     summary="Delete a property definition and all its assignments",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Property definition deleted"),
     *     @OA\Response(response=404, description="Property not found"),
     *     @OA\Response(response=403, description="Forbidden: admin only")
     * )
     */
    $group->delete('/definition/{propertyId}', function (Request $request, Response $response, array $args): Response {
        $propertyId = (int) $args['propertyId'];

        $property = PropertyQuery::create()->findPk($propertyId);
        if ($property === null) {
            throw new HttpNotFoundException($request, gettext('Property not found'));
        }

        // Delete all record assignments first, then the definition
        RecordPropertyQuery::create()->filterByPropertyId($propertyId)->delete();

        $property->delete();

        return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('Property deleted successfully.')]);
    });
})->add(new CSRFMiddleware());
