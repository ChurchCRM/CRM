<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\ResetPasswordEmail;
use ChurchCRM\Emails\ResetPasswordTokenEmail;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/forgot-password', function () use ($app) {
    if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
        $app->get('/reset-request', 'forgotPassword');
        $app->post('/reset-request', 'userPasswordReset');
        $app->get('/set/{token}', function ($request, $response, $args) use ($app) {
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
                    LoggerUtils::getAuthLogger()->info('Password reset for user ' . $user->getUserName());
                    $email = new ResetPasswordEmail($user, $password);
                    if ($email->send()) {
                        return $renderer->render($response, 'password/password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
                    } else {
                        $app->Logger->error($email->getError());

                        throw new \Exception($email->getError());
                    }
                }
            }

            return $renderer->render($response, 'error.php', ['message' => gettext('Unable to reset password')]);
        });
    } else {
        $app->get('/{foo:.*}', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates');

            return $renderer->render($response, '/error.php', ['message' => gettext('Password reset not available.  Please contact your system administrator')]);
        });
    }
});

function forgotPassword($request, $response, $args)
{
    $renderer = new PhpRenderer('templates/password/');
    $pageArgs = [
        'sRootPath'                => SystemURLs::getRootPath(),
        'PasswordResetXHREndpoint' => AuthenticationManager::getForgotPasswordURL(),
    ];

    return $renderer->render($response, 'enter-username.php', $pageArgs);
}

function userPasswordReset(Request $request, Response $response, array $args)
{
    $logger = LoggerUtils::getAppLogger();
    $body = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $userName = strtolower(trim($body->userName));
    if (!empty($userName)) {
        $user = UserQuery::create()->findOneByUserName($userName);
        if (!empty($user) && !empty($user->getEmail())) {
            $token = new Token();
            $token->build('password', $user->getId());
            $token->save();
            $email = new ResetPasswordTokenEmail($user, $token->getToken());
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->error($email->getError());
            }
            LoggerUtils::getAuthLogger()->info('Password reset token for ' . $user->getUserName() . ' sent to email address: ' . $user->getEmail());

            return $response->withStatus(200);
        } else {
            return $response->withStatus(404, gettext('User') . ' [' . $userName . '] ' . gettext('no found or user without an email'));
        }
    }

    return $response->withStatus(400, gettext('UserName not set'));
}
