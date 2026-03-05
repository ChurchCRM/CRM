<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\users\AccountDeletedEmail;
use ChurchCRM\Emails\users\ResetPasswordEmail;
use ChurchCRM\Emails\users\UnlockedEmail;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
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
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/password/reset', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $password = $user->resetPasswordToRandom();
        $user->save();
        $user->createTimeLineNote('password-reset');
        $email = new ResetPasswordEmail($user, $password);
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->warning($email->getError());
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
        $user->setFailedLogins(0);
        $user->save();
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
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted",
     *         @OA\JsonContent(@OA\Property(property="user", type="string", description="Deleted username"))
     *     ),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->delete('/', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $userName = $user->getName();
        $userConfig = UserConfigQuery::create()->findPk($user->getId());
        if ($userConfig !== null) {
            $userConfig->delete();
        }

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
        $userId = $args['userId'];
        $user = UserQuery::create()->findPk($userId);

        return SlimUtils::renderJSON($response, ['user' => $user->getName(), 'userId' => $user->getId(), 'addEvent' => $user->isAddEvent()]);
    });
})->add(AdminRoleAuthMiddleware::class)->add(UserMiddleware::class);
