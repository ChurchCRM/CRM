<?php

use ChurchCRM\Exceptions\ImportantDateRefusedException;
use ChurchCRM\model\ChurchCRM\ImportantDateTypeQuery;
use ChurchCRM\Service\ImportantDateService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

function importantDateTypesPayload(ImportantDateService $service): array
{
    return ['types' => $service->typeSummaries(), 'presets' => $service->availablePresets()];
}

$app->group('/api/important-date-types', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/admin/api/important-date-types",
     *     summary="Important date types with usage counts, and suggested types not yet added",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="types and presets"),
     *     @OA\Response(response=403, description="Admin only")
     * )
     */
    $group->get('', function (Request $request, Response $response): Response {
        return SlimUtils::renderJSON($response, importantDateTypesPayload(new ImportantDateService()));
    });

    /**
     * @OA\Post(
     *     path="/admin/api/important-date-types",
     *     summary="Add a date type, from a suggestion (preset) or by name",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="preset", type="string", description="A key from presets; name and appliesTo come from it"),
     *         @OA\Property(property="name", type="string", maxLength=100),
     *         @OA\Property(property="appliesTo", type="string", enum={"person","family"}),
     *         @OA\Property(property="allowMultiple", type="boolean"),
     *         @OA\Property(property="showOnCalendar", type="boolean")
     *     )),
     *     @OA\Response(response=201, description="types and presets after the change"),
     *     @OA\Response(response=400, description="Invalid name or applies-to"),
     *     @OA\Response(response=409, description="A type with that name exists"),
     *     @OA\Response(response=403, description="Admin only")
     * )
     */
    $group->post('', function (Request $request, Response $response): Response {
        $input = (array) $request->getParsedBody();
        $service = new ImportantDateService();
        try {
            $service->createType(
                (string) ($input['name'] ?? ''),
                (string) ($input['appliesTo'] ?? ''),
                !empty($input['allowMultiple']),
                !array_key_exists('showOnCalendar', $input) || !empty($input['showOnCalendar']),
                isset($input['preset']) ? (string) $input['preset'] : null,
            );
        } catch (ImportantDateRefusedException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->isConflict() ? 409 : 400);
        }

        return SlimUtils::renderJSON($response, importantDateTypesPayload($service), 201);
    })->add(new InputSanitizationMiddleware(['name' => 'text', 'appliesTo' => 'text', 'preset' => 'text']));

    /**
     * @OA\Patch(
     *     path="/admin/api/important-date-types/{id}",
     *     summary="Rename a date type, or change whether it allows several dates, shows on the calendar or is turned on",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="types and presets after the change"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=409, description="Records have several dates of this type"),
     *     @OA\Response(response=403, description="Admin only")
     * )
     */
    $group->patch('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $type = ImportantDateTypeQuery::create()->findPk((int) $args['id']);
        if ($type === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date type not found'), [], 404);
        }
        $input = (array) $request->getParsedBody();
        $changes = array_intersect_key($input, array_flip(['name', 'allowMultiple', 'showOnCalendar', 'active', 'sortOrder']));
        $service = new ImportantDateService();
        try {
            $service->updateType($type, $changes);
        } catch (ImportantDateRefusedException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->isConflict() ? 409 : 400);
        }

        return SlimUtils::renderJSON($response, importantDateTypesPayload($service));
    })->add(new InputSanitizationMiddleware(['name' => 'text']));

    /**
     * @OA\Delete(
     *     path="/admin/api/important-date-types/{id}",
     *     summary="Delete a date type no record uses",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="types and presets after the change"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=409, description="Dates are recorded under this type"),
     *     @OA\Response(response=403, description="Admin only")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $type = ImportantDateTypeQuery::create()->findPk((int) $args['id']);
        if ($type === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date type not found'), [], 404);
        }
        $service = new ImportantDateService();
        try {
            $service->deleteType($type);
        } catch (ImportantDateRefusedException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->isConflict() ? 409 : 400);
        }

        return SlimUtils::renderJSON($response, importantDateTypesPayload($service));
    });
});
