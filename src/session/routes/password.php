<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\UserQuery;
use ChurchCRM\Token;
use ChurchCRM\Emails\ResetPasswordTokenEmail;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\TokenQuery;
use ChurchCRM\dto\SystemConfig;

if (SystemConfig::getBooleanValue('bEnableLostPassword')) {

    $app->group('/password', function () {

        $this->get('/', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates/password/');
            return $renderer->render($response, 'enter-username.php', ['sRootPath' => SystemURLs::getRootPath()]);
        });

        $this->get('/set/{token}', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates/password/');
            $token = TokenQuery::create()->findPk($args['token']);
            $haveUser = false;
            if ($token != null && $token->isPasswordResetToken() && $token->isValid()) {
                $user = UserQuery::create()->findPk($token->getReferenceId());
                $haveUser = empty($user);
                if ($token->getRemainingUses() > 0) {
                    $token->setRemainingUses($token->getRemainingUses() - 1);
                    $token->save();
                    $password = $user->resetPasswordToRandom();
                    $user->save();
                    $email = new ResetPasswordEmail($user, $password);
                    if ($email->send()) {
                        return $renderer->render($response, 'password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
                    } else {
                        $this->Logger->error($email->getError());
                        throw new \Exception($email->getError());
                    }
                }
            }

            return $renderer->render($response, "/../404.php", array("message" => gettext("Unable to reset password")));
        });

    });
}
