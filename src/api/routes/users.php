<?php

// Users APIs
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\UserQuery;
use ChurchCRM\UserConfigQuery;

$app->group('/users', function () {

    $this->post('/{userId:[0-9]+}/password/rest', function ($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $user->updatePasswordDefault();
            $user->save();
            return $response->withStatus(200)->withJson(['status' => "success"]);
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
            return $response->withStatus(200)->withJson(['status' => "success"]);
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
