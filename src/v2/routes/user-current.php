<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/user/current', function (RouteCollectorProxy $group): void {
    $group->get('/manage2fa', 'manage2fa');
    $group->get('/enroll2fa', 'manage2fa'); // backward compatibility
    $group->get('/changepassword', 'changepassword');
    $group->post('/changepassword', 'changepassword')->add(new CSRFMiddleware('user_change_password'));
});

function manage2fa(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user'      => $curUser,
    ];

    return $renderer->render($response, 'manage-2fa.php', $pageArgs);
}

function changepassword(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/');
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user'      => $curUser,
        'isForced'  => $curUser->getNeedPasswordChange(),
    ];

    if ($request->getMethod() === 'POST') {
        $loginRequestBody = $request->getParsedBody();
        $wasForced = $curUser->getNeedPasswordChange();

        try {
            $curUser->userChangePassword($loginRequestBody['OldPassword'], $loginRequestBody['NewPassword1']);

            if ($wasForced) {
                // Forced password change complete — redirect so that ChurchInfoRequiredMiddleware
                // can route the admin to the church-info setup page (or the dashboard if already set).
                return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/dashboard');
            }

            return $renderer->render($response, 'common/success-changepassword.php', $pageArgs);
        } catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s' . $pwChangeExc->AffectedPassword . 'PasswordError'] = $pwChangeExc->getMessage();
        }
    }

    return $renderer->render($response, 'user/changepassword.php', $pageArgs);
}
