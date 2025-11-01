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

$app->group('/user/{userId:[0-9]+}', function (RouteCollectorProxy $group): void {
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

    $group->post('/disableTwoFactor', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $user->disableTwoFactorAuthentication();

        return SlimUtils::renderSuccessJSON($response);
    });

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

    $group->get('/permissions', function (Request $request, Response $response, array $args): Response {
        $userId = $args['userId'];
        $user = UserQuery::create()->findPk($userId);

        return SlimUtils::renderJSON($response, ['user' => $user->getName(), 'userId' => $user->getId(), 'addEvent' => $user->isAddEvent(), 'csvExport' => $user->isCSVExport()]);
    });
})->add(AdminRoleAuthMiddleware::class)->add(UserMiddleware::class);
