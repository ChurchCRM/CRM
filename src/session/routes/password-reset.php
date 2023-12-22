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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/forgot-password', function (RouteCollectorProxy $group): void {
    if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
        $group->get('/reset-request', 'forgotPassword');
        $group->post('/reset-request', 'userPasswordReset');
        $group->get('/set/{token}', function (Request $request, Response $response, array $args): Response {
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
        $group->get('/{foo:.*}', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates');

            return $renderer->render($response, '/error.php', ['message' => gettext('Password reset not available.  Please contact your system administrator')]);
        });
    }
});

function forgotPassword(Request $request, Response $response, array $args): Response
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
    if (empty($userName)) {
        throw new HttpBadRequestException(gettext('UserName not set'));
    }

    $user = UserQuery::create()->findOneByUserName($userName);
    if (empty($user) || empty($user->getEmail())) {
        throw new HttpNotFoundException(gettext('User') . ' [' . $userName . '] ' . gettext('no found or user without an email'));
    }

    $token = new Token();
    $token->build('password', $user->getId());
    $token->save();
    $email = new ResetPasswordTokenEmail($user, $token->getToken());
    if (!$email->send()) {
        $logger->error($email->getError());
    }
    $logger->info('Password reset token for ' . $user->getUserName() . ' sent to email address: ' . $user->getEmail());

    return $response->withStatus(200);
}
