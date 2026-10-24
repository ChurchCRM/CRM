<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\PersonPortalAccessService;
use ChurchCRM\Service\PortalGrantService;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/person/{personId}/sign-in/* (member sign-in design
// §11.3, §8.3, §8.6): remove a remembered device or passkey, and give or remove
// a portal permission. AdminRoleAuthMiddleware gates every route in this app.
$app->group('/api/person/{personId:[0-9]+}/sign-in', function (RouteCollectorProxy $group): void {
    $group->post('/devices/{deviceId:[0-9]+}/remove', 'removePersonDeviceAPI');
    $group->post('/passkeys/{passkeyId:[0-9]+}/remove', 'removePersonPasskeyAPI');
    $group->post('/grants', 'givePersonPortalGrantAPI');
    $group->post('/grants/{grantId:[0-9]+}/remove', 'removePersonPortalGrantAPI');
})->add(new PersonMiddleware())->add(new CSRFMiddleware());

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/devices/{deviceId}/remove",
 *     operationId="removePersonDevice",
 *     summary="Forget one remembered device of a person (Admin role required)",
 *     description="The device can no longer bring back a Member Portal session. Writes a device_removed audit row.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="deviceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="No such person, or no such device of theirs")
 * )
 */
function removePersonDeviceAPI(Request $request, Response $response, array $args): Response
{
    try {
        (new PersonPortalAccessService())->removeDevice($request->getAttribute('person'), AuthenticationManager::getCurrentUser(), (int) $args['deviceId']);
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 404, null, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/passkeys/{passkeyId}/remove",
 *     operationId="removePersonPasskey",
 *     summary="Remove one passkey of a person (Admin role required)",
 *     description="The passkey can no longer sign in. Writes a passkey_removed audit row.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="passkeyId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="No such person, or no such passkey of theirs")
 * )
 */
function removePersonPasskeyAPI(Request $request, Response $response, array $args): Response
{
    try {
        (new PersonPortalAccessService())->removePasskey($request->getAttribute('person'), AuthenticationManager::getCurrentUser(), (int) $args['passkeyId']);
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 404, null, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/grants",
 *     operationId="givePersonPortalGrant",
 *     summary="Give a person a portal permission (Admin role required)",
 *     description="A capability, on a scope of its scope type (none, family, team, ministry or group), with an optional last day and a required reason. Takes effect on the person's next portal request. Writes a grant_added audit row; the reason is kept on the grant only.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"capability", "reason"},
 *         @OA\Property(property="capability", type="string", example="volunteer.team.manage"),
 *         @OA\Property(property="scopeId", type="integer", description="The family, team, ministry or group; omit for a capability without a scope", example=3),
 *         @OA\Property(property="endsOn", type="string", format="date", description="Last day it counts, inclusive", example="2026-12-31"),
 *         @OA\Property(property="reason", type="string", maxLength=255, example="Leads the greeters while Pat is away")
 *     )),
 *     @OA\Response(response=201, description="Given", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="grantId", type="integer")
 *     )),
 *     @OA\Response(response=400, description="An unknown capability, a missing or unknown scope, a date in the past or a missing reason"),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="Person not found"),
 *     @OA\Response(response=409, description="The person already has this permission on this scope")
 * )
 */
function givePersonPortalGrantAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    $input = is_array($input) ? $input : [];
    $text = static fn (string $name): string => is_scalar($input[$name] ?? null) ? (string) $input[$name] : '';

    try {
        $grant = (new PortalGrantService())->give(
            $request->getAttribute('person'),
            AuthenticationManager::getCurrentUser(),
            $text('capability'),
            $text('scopeId') === '' ? null : (int) $text('scopeId'),
            $text('endsOn'),
            $text('reason'),
        );
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 409, null, $request);
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'grantId' => (int) $grant->getId()], 201);
}

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/grants/{grantId}/remove",
 *     operationId="removePersonPortalGrant",
 *     summary="Remove a portal permission an administrator gave a person (Admin role required)",
 *     description="Takes effect on the person's next portal request. Writes a grant_removed audit row.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="grantId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="No such person, or no such given permission of theirs")
 * )
 */
function removePersonPortalGrantAPI(Request $request, Response $response, array $args): Response
{
    try {
        (new PortalGrantService())->remove($request->getAttribute('person'), AuthenticationManager::getCurrentUser(), (int) $args['grantId']);
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 404, null, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}
