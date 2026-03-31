<?php

use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/custom-fields', function (RouteCollectorProxy $group): void {
    $group->get('/person', 'getPersonFieldsByType');
    $group->get('/person/', 'getPersonFieldsByType');
})->add(AdminRoleAuthMiddleware::class);

$app->group('/system/properties', function (RouteCollectorProxy $group): void {
    $group->get('/person', 'getPersonPropertyOptions');
    $group->get('/person/', 'getPersonPropertyOptions');
})->add(AdminRoleAuthMiddleware::class);

/**
 * @OA\Get(
 *     path="/system/custom-fields/person",
 *     summary="Get custom person fields filtered by type ID (Admin role required)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="typeId", in="query", required=true, @OA\Schema(type="integer"),
 *         description="Person type ID to filter custom fields by"
 *     ),
 *     @OA\Response(response=200, description="Array of id/value pairs for matching custom fields",
 *         @OA\JsonContent(type="array", @OA\Items(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="value", type="string")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getPersonFieldsByType(Request $request, Response $response, array $args): Response
{
    $params = $request->getQueryParams();
    $typeId = $params['typeId'];

    $fields = PersonCustomMasterQuery::create()->filterByTypeId($typeId)->find();

    $keyValue = [];

    foreach ($fields as $field) {
        $keyValue[] = ['id' => $field->getId(), 'value' => $field->getName()];
    }

    return SlimUtils::renderJSON($response, $keyValue);
}

/**
 * @OA\Get(
 *     path="/system/properties/person",
 *     summary="Get person property definitions as id/value pairs for settings dropdowns (Admin role required)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of id/value pairs for person properties",
 *         @OA\JsonContent(type="array", @OA\Items(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="value", type="string")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getPersonPropertyOptions(Request $request, Response $response, array $args): Response
{
    $properties = PropertyQuery::create()
        ->filterByProClass('p')
        ->orderByProName()
        ->find();

    $keyValue = [];
    foreach ($properties as $prop) {
        $keyValue[] = ['id' => $prop->getProId(), 'value' => $prop->getProName()];
    }

    return SlimUtils::renderJSON($response, $keyValue);
}
