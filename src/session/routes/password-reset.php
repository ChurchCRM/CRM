<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\PasswordResetService;
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\CSRFUtils;
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
        $group->map(['GET', 'POST'], '/set/{token}', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates');
            $logger = LoggerUtils::getAppLogger();
            $response = $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
            $errorPage = fn (): Response => $renderer->render($response, 'error.php', ['sRootPath' => SystemURLs::getRootPath()]);
            $token = TokenQuery::create()->findPasswordResetToken($args['token']);
            if ($token === null || !$token->isPasswordResetToken() || !$token->isValid()) {
                return $errorPage();
            }

            $user = UserQuery::create()->findPk($token->getReferenceId());
            if ($user === null || $user->getSignInBlockedReason() !== null) {
                return $errorPage();
            }

            $pageArgs = [
                'sRootPath' => SystemURLs::getRootPath(),
                'token' => $args['token'],
                'minPasswordLength' => SystemConfig::getIntValue('iMinPasswordLength'),
                'error' => null,
            ];
            if ($request->getMethod() === 'POST') {
                $body = $request->getParsedBody();
                if (!is_array($body) || !CSRFUtils::verifyRequest($body)) {
                    return $errorPage();
                }

                try {
                    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
                    $confirmation = is_string($body['passwordConfirmation'] ?? null) ? $body['passwordConfirmation'] : '';
                    if ($password !== $confirmation) {
                        throw new PasswordChangeException('New', gettext('Passwords do not match'));
                    }
                    if (!(new PasswordResetService())->reset($token, $user, $password)) {
                        return $errorPage();
                    }
                    $logger->info('Password reset for user ' . $user->getUserName());

                    return $renderer->render($response, 'password/password-check-email.php', ['sRootPath' => SystemURLs::getRootPath()]);
                } catch (PasswordChangeException $error) {
                    $pageArgs['error'] = $error->getMessage();
                }
            }

            return $renderer->render($response, 'password/set-password.php', $pageArgs);
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
