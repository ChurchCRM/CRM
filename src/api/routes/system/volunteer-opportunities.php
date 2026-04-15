<?php

use ChurchCRM\model\ChurchCRM\VolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * REST API for VolunteerOpportunity CRUD — replaces legacy
 * VolunteerOpportunityEditor.php workflow.
 *
 * Mirrors the legacy admin-only permission: the editor redirects home for
 * non-admins, so the API uses AdminRoleAuthMiddleware. Opportunities have
 * a name, description, active enum, and an ordering column — identical to
 * DonationFund's shape — so we expose `active` as a JSON boolean.
 */

/**
 * Convert a VolunteerOpportunity model to a plain array for JSON output.
 */
function volunteerOpportunityToArray(VolunteerOpportunity $opp): array
{
    return [
        'id'          => (int) $opp->getId(),
        'name'        => $opp->getName(),
        'description' => $opp->getDescription(),
        'active'      => $opp->getActive() === 'true',
        'order'       => (int) $opp->getOrder(),
    ];
}

$app->group('/volunteer-opportunities', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/volunteer-opportunities",
     *     summary="List all volunteer opportunities (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="activeOnly", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Array of volunteer opportunities"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $activeOnly = filter_var(
            $request->getQueryParams()['activeOnly'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $query = VolunteerOpportunityQuery::create()->orderByOrder();
        if ($activeOnly) {
            $query->filterByActive('true');
        }

        $out = [];
        foreach ($query->find() as $opp) {
            $out[] = volunteerOpportunityToArray($opp);
        }

        return SlimUtils::renderJSON($response, ['volunteerOpportunities' => $out]);
    });

    /**
     * @OA\Post(
     *     path="/volunteer-opportunities",
     *     summary="Create a new volunteer opportunity (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="description", type="string", maxLength=100),
     *             @OA\Property(property="active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Newly created volunteer opportunity"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        try {
            $input = (array) $request->getParsedBody();
            $name = InputUtils::sanitizeText($input['name'] ?? '');
            $description = InputUtils::sanitizeText($input['description'] ?? '');
            $active = array_key_exists('active', $input)
                ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            if ($name === '') {
                return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
            }

            if (VolunteerOpportunityQuery::create()->findOneByName($name) !== null) {
                return SlimUtils::renderErrorJSON($response, gettext('That name already exists.'), [], 400);
            }

            $maxOrder = VolunteerOpportunityQuery::create()->orderByOrder('desc')->findOne();
            $nextOrder = $maxOrder !== null ? ((int) $maxOrder->getOrder()) + 1 : 1;

            $opp = new VolunteerOpportunity();
            $opp->setName($name);
            $opp->setDescription($description);
            $opp->setActive($active ? 'true' : 'false');
            $opp->setOrder($nextOrder);
            $opp->save();

            return SlimUtils::renderJSON($response, ['volunteerOpportunity' => volunteerOpportunityToArray($opp)], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create volunteer opportunity'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Get(
     *     path="/volunteer-opportunities/{id}",
     *     summary="Get a single volunteer opportunity (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Volunteer opportunity object"),
     *     @OA\Response(response=404, description="Volunteer opportunity not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $opp = VolunteerOpportunityQuery::create()->findPk((int) $args['id']);
        if ($opp === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Volunteer opportunity not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, ['volunteerOpportunity' => volunteerOpportunityToArray($opp)]);
    });

    /**
     * @OA\Put(
     *     path="/volunteer-opportunities/{id}",
     *     summary="Update a volunteer opportunity (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="description", type="string", maxLength=100),
     *             @OA\Property(property="active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated volunteer opportunity"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Volunteer opportunity not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $id = (int) $args['id'];
            $opp = VolunteerOpportunityQuery::create()->findPk($id);
            if ($opp === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Volunteer opportunity not found'), [], 404);
            }

            $input = (array) $request->getParsedBody();

            if (array_key_exists('name', $input)) {
                $name = InputUtils::sanitizeText($input['name']);
                if ($name === '') {
                    return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
                }
                $existing = VolunteerOpportunityQuery::create()->findOneByName($name);
                if ($existing !== null && (int) $existing->getId() !== $id) {
                    return SlimUtils::renderErrorJSON($response, gettext('That name already exists.'), [], 400);
                }
                $opp->setName($name);
            }

            if (array_key_exists('description', $input)) {
                $opp->setDescription(InputUtils::sanitizeText($input['description']));
            }

            if (array_key_exists('active', $input)) {
                $opp->setActive(filter_var($input['active'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
            }

            $opp->save();

            return SlimUtils::renderJSON($response, ['volunteerOpportunity' => volunteerOpportunityToArray($opp)]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update volunteer opportunity'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/volunteer-opportunities/{id}",
     *     summary="Delete a volunteer opportunity (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Volunteer opportunity not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $opp = VolunteerOpportunityQuery::create()->findPk((int) $args['id']);
            if ($opp === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Volunteer opportunity not found'), [], 404);
            }

            $opp->delete();

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete volunteer opportunity'), [], 500, $e, $request);
        }
    });
})->add(AdminRoleAuthMiddleware::class);
