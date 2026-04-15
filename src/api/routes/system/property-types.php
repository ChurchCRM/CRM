<?php

use ChurchCRM\model\ChurchCRM\PropertyType;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\MenuOptionsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * REST API for PropertyType CRUD — replaces legacy PropertyTypeEditor.php /
 * PropertyTypeDelete.php / PropertyTypeList.php workflow.
 *
 * A property type has a single-char class: 'p' (person), 'f' (family), or
 * 'g' (group). Legacy editors enforce this via MenuOptions permission, and
 * we mirror that with MenuOptionsRoleAuthMiddleware.
 */

const PROPERTY_TYPE_VALID_CLASSES = ['p', 'f', 'g'];

/**
 * Convert a PropertyType model to a plain array for JSON output.
 */
function propertyTypeToArray(PropertyType $pt): array
{
    return [
        'id'          => (int) $pt->getPrtId(),
        'class'       => $pt->getPrtClass(),
        'name'        => $pt->getPrtName(),
        'description' => $pt->getPrtDescription(),
    ];
}

$app->group('/property-types', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/property-types",
     *     summary="List all property types (MenuOptions role required)",
     *     tags={"System"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="class", in="query", required=false, description="Filter by class (p/f/g)", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Array of property types"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="MenuOptions role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $class = trim((string) ($request->getQueryParams()['class'] ?? ''));

        $query = PropertyTypeQuery::create()->orderByPrtClass()->orderByPrtName();
        if ($class !== '') {
            if (!in_array($class, PROPERTY_TYPE_VALID_CLASSES, true)) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid class'), [], 400);
            }
            $query->filterByPrtClass($class);
        }

        $out = [];
        foreach ($query->find() as $pt) {
            $out[] = propertyTypeToArray($pt);
        }

        return SlimUtils::renderJSON($response, ['propertyTypes' => $out]);
    });

    /**
     * @OA\Post(
     *     path="/property-types",
     *     summary="Create a new property type (MenuOptions role required)",
     *     tags={"System"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"class","name"},
     *             @OA\Property(property="class", type="string", enum={"p","f","g"}),
     *             @OA\Property(property="name", type="string", maxLength=50),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Newly created property type"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="MenuOptions role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        try {
            $input = (array) $request->getParsedBody();
            $class = strtolower(trim((string) ($input['class'] ?? '')));
            $name = InputUtils::sanitizeText($input['name'] ?? '');
            $description = InputUtils::sanitizeText($input['description'] ?? '');

            if (!in_array($class, PROPERTY_TYPE_VALID_CLASSES, true)) {
                return SlimUtils::renderErrorJSON($response, gettext('Class must be one of p, f, or g'), [], 400);
            }
            if ($name === '') {
                return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
            }

            $pt = new PropertyType();
            $pt->setPrtClass($class);
            $pt->setPrtName($name);
            $pt->setPrtDescription($description);
            $pt->save();

            return SlimUtils::renderJSON($response, ['propertyType' => propertyTypeToArray($pt)], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create property type'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Get(
     *     path="/property-types/{id}",
     *     summary="Get a single property type (MenuOptions role required)",
     *     tags={"System"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Property type object"),
     *     @OA\Response(response=404, description="Property type not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="MenuOptions role required")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $pt = PropertyTypeQuery::create()->findPk((int) $args['id']);
        if ($pt === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Property type not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, ['propertyType' => propertyTypeToArray($pt)]);
    });

    /**
     * @OA\Put(
     *     path="/property-types/{id}",
     *     summary="Update a property type (MenuOptions role required)",
     *     tags={"System"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="class", type="string", enum={"p","f","g"}),
     *             @OA\Property(property="name", type="string", maxLength=50),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated property type"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Property type not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="MenuOptions role required")
     * )
     */
    $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $pt = PropertyTypeQuery::create()->findPk((int) $args['id']);
            if ($pt === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Property type not found'), [], 404);
            }

            $input = (array) $request->getParsedBody();

            if (array_key_exists('class', $input)) {
                $class = strtolower(trim((string) $input['class']));
                if (!in_array($class, PROPERTY_TYPE_VALID_CLASSES, true)) {
                    return SlimUtils::renderErrorJSON($response, gettext('Class must be one of p, f, or g'), [], 400);
                }
                $pt->setPrtClass($class);
            }

            if (array_key_exists('name', $input)) {
                $name = InputUtils::sanitizeText($input['name']);
                if ($name === '') {
                    return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
                }
                $pt->setPrtName($name);
            }

            if (array_key_exists('description', $input)) {
                $pt->setPrtDescription(InputUtils::sanitizeText($input['description']));
            }

            $pt->save();

            return SlimUtils::renderJSON($response, ['propertyType' => propertyTypeToArray($pt)]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update property type'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/property-types/{id}",
     *     summary="Delete a property type (MenuOptions role required)",
     *     tags={"System"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Property type not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="MenuOptions role required")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $pt = PropertyTypeQuery::create()->findPk((int) $args['id']);
            if ($pt === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Property type not found'), [], 404);
            }

            $pt->delete();

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete property type'), [], 500, $e, $request);
        }
    });
})->add(MenuOptionsRoleAuthMiddleware::class);
