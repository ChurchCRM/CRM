<?php

// Users APIs
use ChurchCRM\UserQuery;
use ChurchCRM\UserConfigQuery;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\Emails\AccountDeletedEmail;
use ChurchCRM\Emails\UnlockedEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\User;

$app->group('/users', function () {

    $this->post('/{userId:[0-9]+}/password/reset', function ($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        $user = UserQuery::create()->findPk($args['userId']);
        if (!is_null($user)) {
            $password = $user->resetPasswordToRandom();
            $user->save();
            $user->createTimeLineNote("password-reset");
            $email = new ResetPasswordEmail($user, $password);
            if ($email->send()) {
                return $response->withStatus(200)->withJson(['status' => "success"]);
            } else {
                $this->Logger->error($email->getError());
                throw new \Exception($email->getError());
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
            $user->createTimeLineNote("login-reset");
            $email = new UnlockedEmail($user);
            if (!$email->send()) {
                $this->Logger->warn($email->getError());
            }
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
            $email = new AccountDeletedEmail($user);
            $user->delete();
            if (!$email->send()) {
                $this->Logger->warn($email->getError());
            }
            return $response->withStatus(200)->withJson(['status' => "success"]);
        } else {
            return $response->withStatus(404);
        }
    });
});
