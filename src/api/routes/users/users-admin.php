<?php

use ChurchCRM\Emails\AccountDeletedEmail;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\Emails\UnlockedEmail;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\User;
use ChurchCRM\UserConfigQuery;
use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

$app->group('/users', function () {

    $this->post('/{userId:[0-9]+}/password/reset', function ($request, $response, $args) {
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $password = $user->resetPasswordToRandom();
            $user->save();
            $user->createTimeLineNote("password-reset");
            $email = new ResetPasswordEmail($user, $password);
            if ($email->send()) {
                return $response->withStatus(200);
            } else {
                LoggerUtils::getAppLogger()->error($email->getError());
                throw new \Exception($email->getError());
            }
        } else {
            return $response->withStatus(404, gettext("Bad userId"));
        }
    });

    $this->post('/{userId:[0-9]+}/disableTwoFactor', function ($request, $response, $args) {
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
           $user->disableTwoFactorAuthentication();
        } else {
            return $response->withStatus(404, gettext("Bad userId"));
        }
        return $response->withStatus(200);
    });

    $this->post('/{userId:[0-9]+}/login/reset', function ($request, $response, $args) {
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $user->setFailedLogins(0);
            $user->save();
            $user->createTimeLineNote("login-reset");
            $email = new UnlockedEmail($user);
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->warn($email->getError());
            }
            return $response->withStatus(200);
        } else {
            return $response->withStatus(404, gettext("Bad userId"));
        }
    });

    $this->delete('/{userId:[0-9]+}', function ($request, $response, $args) {
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $userConfig = UserConfigQuery::create()->findPk($user->getId());
            if (!is_null($userConfig)) {
                $userConfig->delete();
            }
            
            $user->delete();
            if (SystemConfig::getBooleanValue("bSendUserDeletedEmail")) {
                $email = new AccountDeletedEmail($user);
                if (!$email->send()) {
                    LoggerUtils::getAppLogger()->warn($email->getError());
                }
            }
            return $response->withJson([]);
        } else {
            return $response->withStatus(404, gettext("Bad userId"));
        }
    });

    $this->get("/{userId:[0-9]+}/permissions", "getUserPermissionsAPI")->add(new AdminRoleAuthMiddleware());

})->add(new AdminRoleAuthMiddleware());

$app->post('/users/{userId:[0-9]+}/apikey/regen', function ($request, $response, $args) {
    $curUser = AuthenticationManager::GetCurrentUser();
    $userId = $args['userId'];
    if (!$curUser->isAdmin() && $curUser->getId() != $userId) {
        return $response->withStatus(403);
    }
    $user = UserQuery::create()->findPk($userId);
    if (!is_null($user)) {
        $user->setApiKey(User::randomApiKey());
        $user->save();
        $user->createTimeLineNote("api-key-regen");
        return $response->withJson(["apiKey" => $user->getApiKey()]);
    } else {
        return $response->withStatus(404, gettext("Bad userId"));
    }
});

function getUserPermissionsAPI(Request $request, Response $response, array $args)
{
    $userId = $args['userId'];
    $user = UserQuery::create()->findPk($userId);
    return $response->withJson(["user" => $user->getName(), "userId" => $user->getId(), "addEvent" => $user->isAddEvent(), "csvExport" => $user->isCSVExport()]);
}
