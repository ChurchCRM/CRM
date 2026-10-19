<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\users\ResetPasswordEmail;
use ChurchCRM\Emails\users\ResetPasswordTokenEmail;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
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
            $tokenValue = (string) ($args['token'] ?? '');
            $token = TokenQuery::create()->findPk($tokenValue);

            if ($token === null || !$token->isPasswordResetToken() || !$token->isValid()) {
                $logger->warning('Password reset confirmation attempted with an invalid or expired token');
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $user = UserQuery::create()->findPk($token->getReferenceId());
            if ($user === null) {
                $logger->warning('Password reset confirmation token references a missing user', [
                    'userId' => $token->getReferenceId(),
                ]);
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            return $renderer->render($response, 'password/password-confirm-reset.php', [
                'sRootPath' => SystemURLs::getRootPath(),
                'token' => $tokenValue,
            ]);
        });

        $group->post('/set/{token}', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates');
            $logger = LoggerUtils::getAppLogger();
            $tokenValue = (string) ($args['token'] ?? '');
            $token = TokenQuery::create()->findPk($tokenValue);

            if ($token === null || !$token->isPasswordResetToken() || !$token->isValid()) {
                $logger->warning('Password reset submission attempted with an invalid or expired token');
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $user = UserQuery::create()->findPk($token->getReferenceId());
            if ($user === null) {
                $logger->warning('Password reset token references a missing user', [
                    'userId' => $token->getReferenceId(),
                ]);
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $password = $user->resetPasswordWithToken($tokenValue);
            if ($password === null) {
                $logger->warning('Password reset token could not be claimed', [
                    'userId' => $user->getId(),
                ]);
                return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $logger->info('Password reset completed', [
                'userId' => $user->getId(),
            ]);

            $email = new ResetPasswordEmail($user, $password);
            if ($email->send()) {
                return $renderer->render($response, 'password/password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
            }

            $logger->error('Failed to send password reset email', [
                'userId' => $user->getId(),
                'error' => $email->getError(),
            ]);
            return $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
        })->add(new CSRFMiddleware());
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

function userPasswordReset(Request $request, Response $response, array $args)
{
    $logger = LoggerUtils::getAppLogger();
    try {
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new HttpBadRequestException($request, gettext('Invalid password reset request.'));
    }
    if (!is_array($body)
        || !isset($body['userName'])
        || !is_string($body['userName'])
        || strlen($body['userName']) > 255) {
        throw new HttpBadRequestException($request, gettext('Invalid password reset request.'));
    }
    $userName = strtolower(trim($body['userName']));
    if ($userName === '') {
        throw new HttpBadRequestException($request, gettext('UserName not set'));
    }

    $user = UserQuery::create()->findOneByUserName($userName);
    if (empty($user) || empty($user->getEmail())) {
        // Use the same response for missing accounts and accounts without email.
        $logger->warning('Password reset requested for an unavailable account');

        return $response->withStatus(200);
    }

    $token = $user->issuePasswordResetToken();
    $email = new ResetPasswordTokenEmail($user, $token->getToken());
    if (!$email->send()) {
        // Undelivered token serves no purpose and only widens the exposure
        // window — drop it so the table doesn't accumulate dead rows.
        $token->delete();
        $logger->error('Password reset email failed for user ' . $user->getUserName() . ': ' . $email->getError());
    } else {
        $logger->info('Password reset token for ' . $user->getUserName() . ' sent to email address: ' . $user->getEmail());
    }

    return $response->withStatus(200);
}
