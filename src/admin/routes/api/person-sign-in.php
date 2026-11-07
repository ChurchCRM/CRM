<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\PersonSignInService;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/person/{personId}/sign-in/* (member sign-in design A13,
// §11.3). AdminRoleAuthMiddleware gates every route in this app.
$app->group('/api/person/{personId:[0-9]+}/sign-in', function (RouteCollectorProxy $group): void {
    $group->post('/disable', 'disablePersonSignInAPI');
    $group->post('/enable', 'enablePersonSignInAPI');
    $group->post('/sign-out-everywhere', 'signOutPersonEverywhereAPI');
})->add(new PersonMiddleware())->add(new CSRFMiddleware());

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/disable",
 *     operationId="disablePersonSignIn",
 *     summary="Turn on Disable sign-in for a person (Admin role required)",
 *     description="Stops the person signing in anywhere, staff or Member Portal. Ends every portal session on its next request, removes their remembered devices and passkeys, and writes a sign_in_disabled audit row. The reason is kept on the person's sign-in record, not in the audit.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string", maxLength=255, example="Asked us to close their access"))),
 *     @OA\Response(response=200, description="Sign-in disabled",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="devicesRemoved", type="integer", example=1),
 *             @OA\Property(property="passkeysRemoved", type="integer", example=0)
 *         )
 *     ),
 *     @OA\Response(response=400, description="The reason is missing or longer than 255 characters"),
 *     @OA\Response(response=403, description="Admin role required, an invalid CSRF token, your own sign-in, or the only administrator who can sign in"),
 *     @OA\Response(response=404, description="Person not found"),
 *     @OA\Response(response=409, description="Sign-in is already disabled")
 * )
 */
function disablePersonSignInAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    $reason = is_array($input) && is_string($input['reason'] ?? null) ? $input['reason'] : '';

    try {
        $removed = (new PersonSignInService())->disable($request->getAttribute('person'), AuthenticationManager::getCurrentUser(), $reason);
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], personSignInRefusalStatus($e), null, $request);
    }

    return SlimUtils::renderJSON($response, ['success' => true] + $removed);
}

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/enable",
 *     operationId="enablePersonSignIn",
 *     summary="Turn off Disable sign-in for a person (Admin role required)",
 *     description="Writes a sign_in_enabled audit row. Removed devices and passkeys do not come back.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(response=200, description="Sign-in enabled", @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="Person not found"),
 *     @OA\Response(response=409, description="Sign-in is not disabled")
 * )
 */
function enablePersonSignInAPI(Request $request, Response $response): Response
{
    try {
        (new PersonSignInService())->enable($request->getAttribute('person'), AuthenticationManager::getCurrentUser());
    } catch (DomainException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], personSignInRefusalStatus($e), null, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/sign-in/sign-out-everywhere",
 *     operationId="signOutPersonEverywhere",
 *     summary="End every Member Portal session of a person (Admin role required)",
 *     description="Each portal session ends on its next request and the person's remembered devices are revoked. Passkeys stay. Writes a session_ended audit row with the reason signed_out_everywhere.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(response=200, description="Signed out everywhere",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="devicesRevoked", type="integer", example=2)
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="Person not found")
 * )
 */
function signOutPersonEverywhereAPI(Request $request, Response $response): Response
{
    $revoked = (new PersonSignInService())->signOutEverywhere($request->getAttribute('person'), AuthenticationManager::getCurrentUser());

    return SlimUtils::renderJSON($response, ['success' => true, 'devicesRevoked' => $revoked]);
}

function personSignInRefusalStatus(DomainException $e): int
{
    return in_array($e->getCode(), [403, 409], true) ? $e->getCode() : 409;
}
