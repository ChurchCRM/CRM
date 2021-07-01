<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\UserQuery;
use ChurchCRM\Token;
use ChurchCRM\Emails\ResetPasswordTokenEmail;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\TokenQuery;
use ChurchCRM\dto\SystemConfig;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Utils\LoggerUtils;


$app->group('/forgot-password', function () {
    if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
        $this->get('/reset-request', "forgotPassword");
        $this->post('/reset-request', 'userPasswordReset');
        $this->get('/set/{token}', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates');
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
                    LoggerUtils::getAuthLogger()->info("Password reset for user ". $user->getUserName());
                    $email = new ResetPasswordEmail($user, $password);
                    if ($email->send()) {
                        return $renderer->render($response, 'password/password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
                    } else {
                        $this->Logger->error($email->getError());
                        throw new \Exception($email->getError());
                    }
                }
            }

            return $renderer->render($response, "error.php", array("message" => gettext("Unable to reset password")));
        });
    }
    else {
        $this->get('/{foo:.*}', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates');
            return $renderer->render($response, '/error.php', array("message" => gettext("Password reset not availble.  Please contact your system administrator")));
        });
    }
});


function forgotPassword($request, $response, $args) {
    $renderer = new PhpRenderer('templates/password/');
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        "PasswordResetXHREndpoint" => AuthenticationManager::GetForgotPasswordURL()
    ];
    return $renderer->render($response, 'enter-username.php', $pageArgs);
}


function userPasswordReset(Request $request, Response $response, array $args)
{
    $logger = LoggerUtils::getAppLogger();
    $body = json_decode($request->getBody());
    $userName = strtolower(trim($body->userName));
    if (!empty($userName)) {
        $user = UserQuery::create()->findOneByUserName($userName);
        if (!empty($user) && !empty($user->getEmail())) {
            $token = new Token();
            $token->build("password", $user->getId());
            $token->save();
            $email = new ResetPasswordTokenEmail($user, $token->getToken());
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->error($email->getError());
            }
            LoggerUtils::getAuthLogger()->info("Password reset token for ". $user->getUserName() . " sent to email address: " . $user->getEmail());
            return $response->withStatus(200);
        } else {
            return $response->withStatus(404, gettext("User") . " [" . $userName . "] ". gettext("no found or user without an email"));
        }
    }
    return $response->withStatus(400, gettext("UserName not set"));
}
