<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/timeline', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/timeline/person/{personId}",
     *     operationId="getPersonTimeline",
     *     summary="Get full timeline for a person",
     *     description="Returns all timeline items (notes and events) for a person, filtered by the current user's permissions. Requires authentication. Notes are only included for users with Notes=1 or Admin. Plain-auth users see only non-note items (calendar events, system events). Private notes are visible only to the author and admins; Notes=1 non-admin users see their own private notes only.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Timeline items",
     *         @OA\JsonContent(
     *             @OA\Property(property="timeline", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->get('/person/{personId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $personId = (int) $args['personId'];
        if (PersonQuery::create()->findPk($personId) === null) {
            throw new HttpNotFoundException($request);
        }

        // ABAC hook: canReadPerson() returns true for all authenticated users today
        // but provides the extension point for future per-record holds.
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canReadPerson($personId)) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        $service = new TimelineService();
        return SlimUtils::renderJSON($response, ['timeline' => $service->getForPerson($personId)]);
    });

    /**
     * @OA\Get(
     *     path="/timeline/family/{familyId}",
     *     operationId="getFamilyTimeline",
     *     summary="Get full timeline for a family",
     *     description="Returns all timeline items (notes) for a family, filtered by the current user's permissions. Requires authentication. Notes are only included for users with Notes=1 or Admin. Plain-auth users see only non-note items. Private notes are visible only to the author and admins.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Timeline items",
     *         @OA\JsonContent(
     *             @OA\Property(property="timeline", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Family not found")
     * )
     */
    $group->get('/family/{familyId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $familyId = (int) $args['familyId'];
        if (FamilyQuery::create()->findPk($familyId) === null) {
            throw new HttpNotFoundException($request);
        }

        // Authorization: enforce family-scope for EditSelf-only users.
        // Fixes GHSA-jjcj-h3cm-p7x7
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canViewFamily($familyId)) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        $service = new TimelineService();
        return SlimUtils::renderJSON($response, ['timeline' => $service->getForFamily($familyId)]);
    });
});
