<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScope;
use ChurchCRM\model\ChurchCRM\VolunteerScopeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerManagerRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — coordinator/team-leader scope (#9706).
 *
 * Two surfaces in one file because they share the rollout gate and nothing else:
 *
 *   /api/volunteer/scopes           who coordinates what. Manager-only (design §3.2):
 *                                   granting authority is the one thing a coordinator
 *                                   must not be able to do for themselves.
 *   /api/volunteer/me/permissions   "what may I manage?". Every authenticated person is
 *                                   potentially a volunteer, so this carries no role gate
 *                                   and derives the acting person from the session — it
 *                                   accepts no personId parameter (§3.3.3).
 *
 * This group chains VolunteerV2EnabledMiddleware itself. Slim 4 scopes ->add() to the one
 * RouteCollectorProxy it is chained on, so nothing propagates from the group in
 * volunteer-status.php; an ungated group would be reachable in every rollout state.
 *
 * Middleware order is LIFO — the last ->add() runs first — so on the scope group the
 * rollout gate answers before the role gate, and the sanitizer runs last, once the caller
 * is known to be allowed in at all.
 */
$app->group('/volunteer', function (RouteCollectorProxy $group): void {
    $group->group('/scopes', function (RouteCollectorProxy $scopes): void {
        $scopes->get('', 'listVolunteerScopes');
        $scopes->post('', 'createVolunteerScope')
            ->add(new InputSanitizationMiddleware([
                'personId' => 'int',
                // The enum type rejects anything outside the two values the column's
                // enum() accepts, so an unknown scope type never reaches a query.
                'scopeType' => 'enum:' . VolunteerScope::TYPE_MINISTRY . ',' . VolunteerScope::TYPE_TEAM,
                'scopeId' => 'int',
            ]));
        $scopes->delete('/{scopeId:[0-9]+}', 'deleteVolunteerScope');
    })->add(VolunteerManagerRoleAuthMiddleware::class);

    $group->get('/me/permissions', 'getMyVolunteerPermissions');
})->add(new VolunteerV2EnabledMiddleware());

/**
 * Shape one scope row for the wire, resolving the polymorphic target's name so a client
 * does not have to make a second call per row to render a readable list.
 */
function volunteerScopeToArray(VolunteerScope $scope): array
{
    $scopeId = (int) $scope->getScopeId();
    $target = $scope->getScopeType() === VolunteerScope::TYPE_TEAM
        ? VolunteerTeamQuery::create()->findPk($scopeId)
        : VolunteerMinistryQuery::create()->findPk($scopeId);

    $person = PersonQuery::create()->findPk((int) $scope->getPersonId());

    return [
        'id' => (int) $scope->getId(),
        'personId' => (int) $scope->getPersonId(),
        'personName' => $person !== null ? $person->getFullName() : '',
        'scopeType' => $scope->getScopeType(),
        'scopeId' => $scopeId,
        // Null when the polymorphic target has been deleted. The column carries no
        // foreign key (§2.15), so an orphan row is possible and must not blow up a listing.
        'scopeName' => $target !== null ? $target->getName() : null,
        'grantedDate' => $scope->getGrantedDate('Y-m-d H:i:s'),
        'grantedByPersonId' => $scope->getGrantedByPersonId() === null
            ? null
            : (int) $scope->getGrantedByPersonId(),
    ];
}

/**
 * @OA\Get(
 *     path="/volunteer/scopes",
 *     operationId="listVolunteerScopes",
 *     summary="List volunteer coordinator and team-leader scope grants",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="query", required=false, @OA\Schema(type="integer"),
 *         description="Only grants held by this person"),
 *     @OA\Parameter(name="ministryId", in="query", required=false, @OA\Schema(type="integer"),
 *         description="Only ministry-scope grants on this ministry"),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer"),
 *         description="Only team-scope grants on this team"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer manager access is required, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="scopes", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerScopes(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $personId = isset($params['personId']) ? (int) $params['personId'] : null;
    $ministryId = isset($params['ministryId']) ? (int) $params['ministryId'] : null;
    $teamId = isset($params['teamId']) ? (int) $params['teamId'] : null;

    $authz = new VolunteerAuthorizationService();
    $scopes = $authz->listScopes($personId, $ministryId, $teamId);

    return SlimUtils::renderJSON($response, [
        'scopes' => array_map('volunteerScopeToArray', $scopes),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/scopes",
 *     operationId="createVolunteerScope",
 *     summary="Grant ministry-coordinator or team-leader authority to a person",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personId","scopeType","scopeId"},
 *         @OA\Property(property="personId", type="integer"),
 *         @OA\Property(property="scopeType", type="string", enum={"ministry","team"}),
 *         @OA\Property(property="scopeId", type="integer", description="Ministry id or team id")
 *     )),
 *     @OA\Response(response=400, description="Missing or invalid field"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer manager access is required, or V2 is not enabled"),
 *     @OA\Response(response=404, description="The person or the scope target does not exist"),
 *     @OA\Response(response=200, description="The grant already existed; the same row is returned"),
 *     @OA\Response(response=201, description="Granted")
 * )
 */
function createVolunteerScope(Request $request, Response $response): Response
{
    $input = (array) $request->getParsedBody();
    $personId = (int) $input['personId'];
    $scopeType = (string) $input['scopeType'];
    $scopeId = (int) $input['scopeId'];

    $authz = new VolunteerAuthorizationService();

    if (!$authz->personExists($personId)) {
        return SlimUtils::renderErrorJSON($response, gettext('Person not found'), [], 404, null, $request);
    }

    // The scope target column is polymorphic and therefore carries no foreign key
    // (§2.15), so this check is the only referential integrity there is.
    if (!$authz->scopeTargetExists($scopeType, $scopeId)) {
        $message = $scopeType === VolunteerScope::TYPE_TEAM
            ? gettext('Team not found')
            : gettext('Ministry not found');

        return SlimUtils::renderErrorJSON($response, $message, [], 404, null, $request);
    }

    $existing = VolunteerScopeQuery::create()
        ->filterByPersonId($personId)
        ->filterByScopeType($scopeType)
        ->filterByScopeId($scopeId)
        ->findOne();

    $grantedBy = (int) AuthenticationManager::getCurrentUser()->getId();

    try {
        $scope = $authz->grantScope($personId, $scopeType, $scopeId, $grantedBy);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Could not grant the scope'), [], 500, $e, $request);
    }

    // Idempotent by the vscp_person_scope_uidx unique key: a repeat grant is 200 with the
    // same id, never 409 and never a second row (design §6.6).
    return SlimUtils::renderJSON(
        $response,
        ['scope' => volunteerScopeToArray($scope)],
        $existing === null ? 201 : 200
    );
}

/**
 * @OA\Delete(
 *     path="/volunteer/scopes/{scopeId}",
 *     operationId="deleteVolunteerScope",
 *     summary="Revoke a volunteer coordinator or team-leader scope grant",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scopeId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer manager access is required, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such grant"),
 *     @OA\Response(response=200, description="Revoked")
 * )
 */
function deleteVolunteerScope(Request $request, Response $response, array $args): Response
{
    $scope = VolunteerScopeQuery::create()->findPk((int) $args['scopeId']);
    if ($scope === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Scope grant not found'), [], 404, null, $request);
    }

    try {
        (new VolunteerAuthorizationService())->revokeScope($scope);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Could not revoke the scope'), [], 500, $e, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Get(
 *     path="/volunteer/me/permissions",
 *     operationId="getMyVolunteerPermissions",
 *     summary="What volunteer structure may the authenticated person manage?",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer Management V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="isAdmin", type="boolean"),
 *             @OA\Property(property="isGlobalManager", type="boolean"),
 *             @OA\Property(property="isCoordinator", type="boolean"),
 *             @OA\Property(property="isTeamLeader", type="boolean"),
 *             @OA\Property(property="managedMinistryIds", type="array", @OA\Items(type="integer")),
 *             @OA\Property(property="managedTeamIds", type="array", @OA\Items(type="integer"))
 *         )
 *     )
 * )
 */
function getMyVolunteerPermissions(Request $request, Response $response): Response
{
    $currentUser = AuthenticationManager::getCurrentUser();
    $authz = new VolunteerAuthorizationService();

    LoggerUtils::getAppLogger()->debug('Volunteer permissions requested', [
        'personId' => $currentUser->getId(),
    ]);

    // Explicit grants only — an administrator or global manager holds none, which is why
    // isGlobalManager is reported separately rather than being folded into the id lists.
    //
    // `isCoordinator` and `isTeamLeader` (#9867) are the two tier answers a client
    // cannot derive from the id lists, and they differ for exactly the persona the
    // Member Portal's revision of D14 is about:
    //
    //   - a SELF-SERVICE login holding a `team` grant is `isTeamLeader: true` and
    //     `isCoordinator: false`. It leads its team from the Member Portal and the
    //     admin dashboard stays shut to it;
    //   - a coordinator or manager is `isCoordinator: true` and `isTeamLeader: false`:
    //     they administer the ministry above the team rather than leading one
    //     (volunteer design §4.4), so `managedTeamIds` can be long while
    //     `isTeamLeader` is false.
    return SlimUtils::renderJSON($response, [
        'isAdmin' => $currentUser->isAdmin(),
        'isGlobalManager' => $authz->isGlobalManager($currentUser),
        'isCoordinator' => $currentUser->isVolunteerCoordinatorEnabled(),
        'isTeamLeader' => $currentUser->isVolunteerTeamLeaderEnabled(),
        'managedMinistryIds' => $authz->getManagedMinistryIds($currentUser),
        'managedTeamIds' => $authz->getManagedTeamIds($currentUser),
    ]);
}
