<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\users\ResetPasswordEmail;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/forgot-password', function (RouteCollectorProxy $group): void {
    if (SystemConfig::getBooleanValue('bEnableLostPassword') && SystemConfig::isEmailEnabled()) {
        $group->get('/reset-request', 'forgotPassword');
        $group->post('/reset-request', 'userPasswordReset');
        $group->get('/set/{token}', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates');
            $logger = LoggerUtils::getAppLogger();
            $token = TokenQuery::create()->findPk($args['token']);
            
            // Validate token and reset password
            if ($token === null) {
                $logger->warning('Password reset attempted with non-existent token: ' . $args['token']);
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }
            
            if (!$token->isPasswordResetToken() || !$token->isValid()) {
                $logger->warning('Password reset attempted with invalid token: ' . $args['token']);
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }
            
            $user = UserQuery::create()->findPk($token->getReferenceId());
            if ($user === null) {
                $logger->warning('Password reset token found but associated user does not exist: ' . $token->getReferenceId());
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }
            
            if (!$token->consume()) {
                $logger->warning('Password reset token could not be consumed for user: ' . $user->getUserName());
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }
            
            if ($user->getSignInBlockedReason() !== null) {
                $logger->warning('Password reset token used for an account that cannot sign in: ' . $user->getUserName());
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $password = $user->resetPasswordToRandom();
            $user->save();
            $logger->info('Password reset for user ' . $user->getUserName());
            
            $email = new ResetPasswordEmail($user, $password);
            if ($email->send()) {
                return $renderer->render($response, 'password/password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
            } else {
                $logger->error('Failed to send password reset email for user ' . $user->getUserName() . ': ' . $email->getError());
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }
        });
    } else {
        $group->get('/{foo:.*}', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates');
            $message = SystemConfig::getBooleanValue('bEnableLostPassword')
                ? gettext('Password reset is unavailable because email is disabled. Please contact your system administrator.')
                : gettext('Password reset not available.  Please contact your system administrator');

            return $renderer->render($response, '/error.php', ['message' => $message]);
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

function userPasswordReset(Request $request, Response $response, array $args): Response
{
    $body = json_decode($request->getBody(), true, 512);
    $userName = trim((string) ($body['userName'] ?? ''));
    if ($userName === '') {
        throw new HttpBadRequestException($request, gettext('UserName not set'));
    }

    (new UserService())->requestPasswordReset($userName);

    return $response->withStatus(200);
}
