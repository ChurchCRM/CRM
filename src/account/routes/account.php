<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\PortalAccountPages;
use ChurchCRM\Service\LoginAsPersonHistoryService;
use ChurchCRM\Service\UserPermissionsService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('', 'viewOwnAccount');
$app->get('/', 'viewOwnAccount');
$app->get('/2fa', 'viewOwnTwoFactor');
$app->get('/password', 'changeOwnPassword');
$app->post('/password', 'changeOwnPassword')->add(new CSRFMiddleware('user_change_password'));

function viewOwnAccount(Request $request, Response $response): Response
{
    $user = AuthenticationManager::getCurrentUser();

    return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'account.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
        'permissionGroups' => (new UserPermissionsService())->getEffectivePermissions($user),
        'loginAsPersonHistory' => $user->isAdmin() ? (new LoginAsPersonHistoryService())->cardForUser((int) $user->getId()) : null,
        'aBreadcrumbs' => PageHeader::breadcrumbs([[gettext('My Account')]]),
    ]);
}

function viewOwnTwoFactor(Request $request, Response $response): Response
{
    return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'two-factor.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => AuthenticationManager::getCurrentUser(),
    ]);
}

function changeOwnPassword(Request $request, Response $response): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $user = AuthenticationManager::getCurrentUser();
    $isForced = $user->getNeedPasswordChange();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
        'isForced' => $isForced,
    ];

    if ($request->getMethod() === 'POST') {
        try {
            PortalAccountPages::applyPasswordChange($user, $request->getParsedBody());
            if ($isForced) {
                // The dashboard runs ChurchInfoRequiredMiddleware, which sends an
                // administrator of a fresh install to church-info first.
                return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/dashboard');
            }

            return $renderer->render($response, 'password-changed.php', $pageArgs);
        } catch (PasswordChangeException $passwordChangeException) {
            $pageArgs['s' . $passwordChangeException->AffectedPassword . 'PasswordError'] = $passwordChangeException->getMessage();
        }
    }

    return $renderer->render($response, 'password.php', $pageArgs);
}
