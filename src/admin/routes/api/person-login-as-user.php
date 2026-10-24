<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\LoginAsUser;
use ChurchCRM\Portal\Auth\PortalSignInRefusedException;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\LoginAsUserEnabledMiddleware;
use ChurchCRM\Slim\Middleware\SessionOnlyMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Served at /admin/api/person/{personId}/login-as-user (member sign-in design
// §6.5). AdminRoleAuthMiddleware gates every route in this app. Stop is a portal
// route: src/portal/routes/login-as-user.php.
$app->post('/api/person/{personId:[0-9]+}/login-as-user', 'startLoginAsUserAPI')
    ->add(new PersonMiddleware())
    ->add(new CSRFMiddleware())
    ->add(new SessionOnlyMiddleware())
    ->add(new LoginAsUserEnabledMiddleware());

/**
 * @OA\Post(
 *     path="/admin/api/person/{personId}/login-as-user",
 *     operationId="startLoginAsUser",
 *     summary="Open the Member Portal as this person (Admin role, browser session only)",
 *     description="Starts a Login as User session: the administrator's staff session waits, staff pages are closed until Stop, and every write is recorded with both people. Read-only unless Allow changes during Login as User is on.",
 *     tags={"Admin"},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=2)),
 *     @OA\Response(response=200, description="Started",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="redirect", type="string", example="/portal/")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required, an invalid CSRF token, an API key, or the person is deceased, has Disable sign-in or has a staff account"),
 *     @OA\Response(response=404, description="Allow Login as User is off, or the person was not found")
 * )
 */
function startLoginAsUserAPI(Request $request, Response $response): Response
{
    try {
        LoginAsUser::start($request->getAttribute('person'), AuthenticationManager::getCurrentUser());
    } catch (PortalSignInRefusedException $e) {
        return SlimUtils::renderErrorJSON($response, LoginAsUser::refusalMessage($e->reason), [], 403, null, $request);
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'redirect' => SystemURLs::getRootPath() . '/portal/']);
}
