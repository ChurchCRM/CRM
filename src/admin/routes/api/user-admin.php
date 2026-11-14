<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\SignInThrottle;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\users\AccountDeletedEmail;
use ChurchCRM\Emails\users\ResetPasswordEmail;
use ChurchCRM\Emails\users\UnlockedEmail;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\Service\PersonSignInService;
use ChurchCRM\Service\UserService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Api\UserMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/user/{userId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Post(
     *     path="/api/user/{userId}/password/reset",
     *     summary="Reset a user's password to a random value and email it to them (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Password reset and email sent"),
     *     @OA\Response(response=403, description="Admin role required"),
     *     @OA\Response(
     *         response=409,
     *         description="Email delivery is disabled or SMTP is not configured, so the reset was refused",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Password was rotated but the email could not be delivered; the user is effectively locked out",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    $group->post('/password/reset', function (Request $request, Response $response, array $args): Response {
        if (!SystemConfig::isEmailEnabled()) {
            // Don't reset if we can't deliver the new credentials — the user
            // would be locked out. Admin should use "Change Password" instead
            // or configure email first.
            return SlimUtils::renderJSON(
                $response->withStatus(409),
                ['success' => false, 'error' => gettext('Email is disabled. Configure email before resetting passwords, or use Change Password.')]
            );
        }
        $user = $request->getAttribute('user');
        $password = $user->resetPasswordToRandom();
        $user->save();
        $user->createTimeLineNote('password-reset');
        $email = new ResetPasswordEmail($user, $password);
        if (!$email->send()) {
            // Password is already rotated in the DB and the user did not receive
            // it — they are effectively locked out. Surface a 500 to the admin
            // UI so it's clear the action failed and manual follow-up is needed.
            LoggerUtils::getAppLogger()->error('Password reset email failed for user ' . $user->getUserName() . ': ' . $email->getError());
            return SlimUtils::renderJSON(
                $response->withStatus(500),
                ['success' => false, 'error' => gettext('Password was reset but the email could not be sent. The user is locked out — share the new password manually or use Change Password.')]
            );
        }

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/api/user/{userId}/disableTwoFactor",
     *     summary="Disable two-factor authentication for a user (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="2FA disabled for the user"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/disableTwoFactor', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $user->disableTwoFactorAuthentication();

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/api/user/{userId}/login/reset",
     *     summary="Reset failed login counter and send unlock email (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Login counter reset and unlock email sent"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/login/reset', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        SignInThrottle::unlockAccount($user);
        $user->createTimeLineNote('login-reset');
        $email = new UnlockedEmail($user);
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->warning($email->getError());
        }

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Delete(
     *     path="/api/user/{userId}/",
     *     summary="Delete a user account (Admin role required)",
     *     description="Removes the login only; the person and everything they entered stay. With disableSignIn true, also turns on Disable sign-in for the person so they cannot use the Member Portal.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="disableSignIn", type="boolean", default=false))),
     *     @OA\Response(response=200, description="User deleted",
     *         @OA\JsonContent(@OA\Property(property="user", type="string", description="Deleted username"))
     *     ),
     *     @OA\Response(response=403, description="Admin role required, your own account, or the last administrator")
     * )
     */
    $group->delete('/', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $currentUser = AuthenticationManager::getCurrentUser();

        // Never delete yourself or the last administrator (#8672).
        $blockedReason = (new UserService())->getUserDeletionBlockedReason($user, $currentUser);
        if ($blockedReason !== null) {
            return SlimUtils::renderErrorJSON($response, $blockedReason, [], 403);
        }

        // Deleting the login removes staff access only. With disableSignIn,
        // also turn on Disable sign-in so the person can't keep using the
        // Member Portal with an email link, device or passkey (#8672).
        $input = $request->getParsedBody();
        $disableSignIn = is_array($input) && ($input['disableSignIn'] ?? false) === true;
        $person = $user->getPerson();
        if ($disableSignIn && $person !== null && $currentUser !== null) {
            try {
                (new PersonSignInService())->disable($person, $currentUser, gettext('Login deleted'));
            } catch (DomainException $e) {
                if ($e->getCode() !== 409) {
                    return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 403);
                }
            }
        }

        $userName = $user->getName();
        UserConfigQuery::create()->filterByPeronId($user->getId())->delete();

        $user->delete();
        if (SystemConfig::getBooleanValue('bSendUserDeletedEmail')) {
            $email = new AccountDeletedEmail($user);
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->warning($email->getError());
            }
        }

        return SlimUtils::renderJSON($response, ['user' => $userName]);
    });

    /**
     * @OA\Get(
     *     path="/api/user/{userId}/permissions",
     *     summary="Get permission flags for a user (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User permission data",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="string"),
     *             @OA\Property(property="userId", type="integer"),
     *             @OA\Property(property="addEvent", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/permissions', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');

        return SlimUtils::renderJSON($response, ['user' => $user->getName(), 'userId' => $user->getId(), 'addEvent' => $user->isAddEvent()]);
    });
})->add(AdminRoleAuthMiddleware::class)->add(UserMiddleware::class);
