<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
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

/**
 * Whether this session belongs to a member rather than to staff.
 *
 * These two pages are the only part of the application outside `/portal` that
 * a self-service login may open — `AuthMiddleware::isLimitedAccessAllowedPath()`
 * exempts them so a forced password change cannot lock somebody out. Rendering
 * them with `Include/Header.php` would put the admin shell in front of a member,
 * which the Member Portal's product principle forbids, so for that session they
 * are drawn with the portal's own layout instead: same URL, same form fields,
 * same POST handler, same CSRF form id (issue #9865, design §5.2).
 */
function isMemberPortalSession(): bool
{
    $user = AuthenticationManager::getCurrentUser();

    return $user instanceof User && $user->isEditSelfExclusive();
}

function manage2fa(Request $request, Response $response, array $args): Response
{
    $curUser = AuthenticationManager::getCurrentUser();

    if (isMemberPortalSession()) {
        PortalTwig::preparePage();

        return PortalTwig::render(
            $response,
            'profile/two-factor.html.twig',
            ['pageTitle' => gettext('Two-Factor Authentication')],
            PortalNav::PROFILE
        );
    }

    $renderer = new PhpRenderer('templates/user/');
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
    $isPortal = isMemberPortalSession();
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
                // A member is bounced from there to /portal by AuthMiddleware.
                return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/dashboard');
            }

            if ($isPortal) {
                return renderPortalPasswordPage($response, 'profile/password-changed.html.twig', []);
            }

            return $renderer->render($response, 'common/success-changepassword.php', $pageArgs);
        } catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s' . $pwChangeExc->AffectedPassword . 'PasswordError'] = $pwChangeExc->getMessage();
        }
    }

    if ($isPortal) {
        return renderPortalPasswordPage($response, 'profile/password.html.twig', [
            'minPasswordLength' => SystemConfig::getIntValue('iMinPasswordLength'),
            'oldPasswordError' => $pageArgs['sOldPasswordError'] ?? '',
            'newPasswordError' => $pageArgs['sNewPasswordError'] ?? '',
        ]);
    }

    return $renderer->render($response, 'user/changepassword.php', $pageArgs);
}

/**
 * Render one of the two portal-layout password pages into a fresh response.
 *
 * @param array<string, mixed> $model
 */
function renderPortalPasswordPage(Response $response, string $template, array $model): Response
{
    PortalTwig::preparePage();

    return PortalTwig::render(
        $response,
        $template,
        array_merge(['pageTitle' => gettext('Change your password')], $model),
        PortalNav::PROFILE
    );
}
