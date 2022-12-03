<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\AccountDeletedEmail;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\Emails\UnlockedEmail;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use ChurchCRM\UserConfigQuery;
use ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/user/{userId:[0-9]+}', function () {
    $this->post("/password/reset", "resetPasswordAPI");
    $this->post('/disableTwoFactor', "disableTwoFactor");
    $this->post('/login/reset', "resetLogin");
    $this->delete('/', "deleteUser");
    $this->get("/permissions", "getUserPermissionsAPI");
})->add(new AdminRoleAuthMiddleware())->add(new UserAPIMiddleware());

function resetPasswordAPI(Request $request, Response $response, array $args)
{

    $user = $request->getAttribute("user");
    $password = $user->resetPasswordToRandom();
    $user->save();
    $user->createTimeLineNote("password-reset");
    $email = new ResetPasswordEmail($user, $password);
    if ($email->send()) {
        return $response->withStatus(200);
    } else {
        LoggerUtils::getAppLogger()->error($email->getError());
        throw new Exception($email->getError());
    }
}

function disableTwoFactor(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $user->disableTwoFactorAuthentication();
    return $response->withStatus(200);
}

function resetLogin(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $user->setFailedLogins(0);
    $user->save();
    $user->createTimeLineNote("login-reset");
    $email = new UnlockedEmail($user);
    if (!$email->send()) {
        LoggerUtils::getAppLogger()->warning($email->getError());
    }
    return $response->withStatus(200);
}

function deleteUser(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $userName = $user->getName();
    $userConfig = UserConfigQuery::create()->findPk($user->getId());
    if (!is_null($userConfig)) {
        $userConfig->delete();
    }

    $user->delete();
    if (SystemConfig::getBooleanValue("bSendUserDeletedEmail")) {
        $email = new AccountDeletedEmail($user);
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->warning($email->getError());
        }
    }
    return $response->withJson(["user" => $userName]);
}


function getUserPermissionsAPI(Request $request, Response $response, array $args)
{
    $userId = $args['userId'];
    $user = UserQuery::create()->findPk($userId);
    return $response->withJson(["user" => $user->getName(), "userId" => $user->getId(), "addEvent" => $user->isAddEvent(), "csvExport" => $user->isCSVExport()]);
}
