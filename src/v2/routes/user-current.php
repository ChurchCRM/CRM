<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\PortalAccountPages;
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
 * The Member Portal owns the account pages now — `/portal/profile/password` and
 * `/portal/profile/two-factor`, which wear the portal layout for every role —
 * and those are what the portal links to. These two URLs survive because the
 * *forced* flows send people here: `LocalAuthentication` returns them as
 * `nextStepURL` for a first-login password change or a required 2FA enrollment,
 * and `AuthMiddleware::isLimitedAccessAllowedPath()` exempts them by name so a
 * member cannot be locked out. They cannot simply redirect to the portal URLs —
 * the forced flows break their own redirect loop by matching
 * `/v2/user/current/changepassword` against `REQUEST_URI`, so a redirect would
 * bounce the browser between the two paths forever.
 *
 * So the behaviour here is unchanged: a self-service session is drawn with the
 * portal's own layout, staff get the admin shell. What changed is that the
 * portal rendering goes through `PortalAccountPages`, the one place a portal
 * account page is turned into a response (issue #9865, design §5.2).
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
        return PortalAccountPages::renderTwoFactor($response);
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
            PortalAccountPages::applyPasswordChange($curUser, $loginRequestBody);

            if ($wasForced) {
                // Forced password change complete — redirect so that ChurchInfoRequiredMiddleware
                // can route the admin to the church-info setup page (or the dashboard if already set).
                // A member is bounced from there to /portal by AuthMiddleware.
                return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/v2/dashboard');
            }

            if ($isPortal) {
                return PortalAccountPages::renderPasswordChanged($response);
            }

            return $renderer->render($response, 'common/success-changepassword.php', $pageArgs);
        } catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s' . $pwChangeExc->AffectedPassword . 'PasswordError'] = $pwChangeExc->getMessage();
        }
    }

    if ($isPortal) {
        // The form posts back to this URL, not to the portal's own page: a
        // forced password change is pinned here until it completes, and a POST
        // sent to /portal/profile/password would be bounced straight back by
        // AuthMiddleware's nextStepURL redirect.
        return PortalAccountPages::renderPasswordForm(
            $response,
            SystemURLs::getRootPath() . '/v2/user/current/changepassword',
            $pageArgs['sOldPasswordError'] ?? '',
            $pageArgs['sNewPasswordError'] ?? ''
        );
    }

    return $renderer->render($response, 'user/changepassword.php', $pageArgs);
}
