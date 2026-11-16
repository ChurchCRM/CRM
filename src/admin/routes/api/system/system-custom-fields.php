<?php

use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Service\CustomFieldDefinitionService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/custom-fields', function (RouteCollectorProxy $group): void {
    $group->get('/person', 'getPersonFieldsByType');
    $group->get('/person/', 'getPersonFieldsByType');
    $group->put('/{recordType:person|family}/{field:c[0-9]+}/order', 'moveCustomField');
    $group->delete('/{recordType:person|family}/{field:c[0-9]+}', 'deleteCustomField');
});

$app->group('/api/system/properties', function (RouteCollectorProxy $group): void {
    $group->get('/person', 'getPersonPropertyOptions');
    $group->get('/person/', 'getPersonPropertyOptions');
});

/**
 * @OA\Get(
 *     path="/admin/api/system/custom-fields/person",
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
    try {
        $params = $request->getQueryParams();
        $typeId = (int) ($params['typeId'] ?? 0);

        $fields = PersonCustomMasterQuery::create()->filterByTypeId($typeId)->find();

        $keyValue = [];

        foreach ($fields as $field) {
            $keyValue[] = ['id' => $field->getId(), 'value' => $field->getName()];
        }

        return SlimUtils::renderJSON($response, $keyValue);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve data'), [], 500, $e, $request);
    }
}

/**
 * @OA\Get(
 *     path="/admin/api/system/properties/person",
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
    try {
        $properties = PropertyQuery::create()
            ->filterByProClass('p')
            ->orderByProName()
            ->find();

        $keyValue = [];
        foreach ($properties as $prop) {
            $keyValue[] = ['id' => $prop->getProId(), 'value' => $prop->getProName()];
        }

        return SlimUtils::renderJSON($response, $keyValue);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve data'), [], 500, $e, $request);
    }
}

/**
 * @OA\Put(
 *     path="/admin/api/system/custom-fields/{recordType}/{field}/order",
 *     summary="Move a person or family custom field up or down one place (Admin role required)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="recordType", in="path", required=true, @OA\Schema(type="string", enum={"person", "family"})),
 *     @OA\Parameter(name="field", in="path", required=true, @OA\Schema(type="string", pattern="^c[0-9]+$", example="c3"),
 *         description="Custom field column name"
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"direction"},
 *             @OA\Property(property="direction", type="string", enum={"up", "down"})
 *         )
 *     ),
 *     @OA\Response(response=200, description="Field moved"),
 *     @OA\Response(response=400, description="Invalid direction, or no field in that direction"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=404, description="Custom field not found")
 * )
 */
function moveCustomField(Request $request, Response $response, array $args): Response
{
    try {
        $direction = ((array) $request->getParsedBody())['direction'] ?? '';
        if (!in_array($direction, CustomFieldDefinitionService::DIRECTIONS, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Direction must be "up" or "down"'), [], 400);
        }

        $service = new CustomFieldDefinitionService();
        $definition = $service->find($args['recordType'], $args['field']);
        if ($definition === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Custom field not found'), [], 404);
        }

        if (!$service->move($definition, $direction)) {
            return SlimUtils::renderErrorJSON($response, gettext('Cannot move field in that direction'), [], 400);
        }

        return SlimUtils::renderSuccessJSON($response);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to move custom field'), [], 500, $e, $request);
    }
}

/**
 * @OA\Delete(
 *     path="/admin/api/system/custom-fields/{recordType}/{field}",
 *     summary="Delete a person or family custom field and all its stored values (Admin role required)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="recordType", in="path", required=true, @OA\Schema(type="string", enum={"person", "family"})),
 *     @OA\Parameter(name="field", in="path", required=true, @OA\Schema(type="string", pattern="^c[0-9]+$", example="c3"),
 *         description="Custom field column name"
 *     ),
 *     @OA\Response(response=200, description="Field deleted"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=404, description="Custom field not found")
 * )
 */
function deleteCustomField(Request $request, Response $response, array $args): Response
{
    try {
        $service = new CustomFieldDefinitionService();
        $definition = $service->find($args['recordType'], $args['field']);
        if ($definition === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Custom field not found'), [], 404);
        }

        $service->delete($definition);

        return SlimUtils::renderSuccessJSON($response);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to delete custom field'), [], 500, $e, $request);
    }
}
