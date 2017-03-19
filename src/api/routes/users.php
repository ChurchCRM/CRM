<?php

// Users APIs
use ChurchCRM\UserQuery;
use ChurchCRM\UserConfigQuery;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\Emails\UnlockedEmail;
use ChurchCRM\dto\SystemConfig;

$app->group('/users', function () {

    $this->post('/{userId:[0-9]+}/password/reset', function ($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $password = SystemConfig::getValue('sDefault_Pass');
            $user->updatePassword($password);
            $user->setNeedPasswordChange(true);;
            $user->save();
            $email = new ResetPasswordEmail($user, $password);
            if ($email->send()) {
                return $response->withStatus(200)->withJson(['status' => "success"]);
            } else {
                return $response->withStatus(404)->getBody()->write($email->getError());
            }
        } else {
            return $response->withStatus(404);
        }
    });

    $this->post('/{userId:[0-9]+}/login/reset', function ($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $user->setFailedLogins(0);
            $user->save();
            $email = new UnlockedEmail($user);
            if ($email->send()) {
                return $response->withStatus(200)->withJson(['status' => "success"]);
            } else {
                return $response->withStatus(404)->getBody()->write($email->getError());
            }
        } else {
            return $response->withStatus(404);
        }
    });

    $this->delete('/{userId:[0-9]+}', function ($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $userConfig =  UserConfigQuery::create()->findPk($user->getId());
            if (!is_null($userConfig)) {
                $userConfig->delete();
            }
            $user->delete();
            return $response->withStatus(200)->withJson(['status' => "success"]);
        } else {
            return $response->withStatus(404);
        }
    });
});
