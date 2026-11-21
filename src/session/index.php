<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\StaffSignInPage;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Service\DashboardLandingService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

// Get base path by combining $sRootPath from Config.php with /session endpoint
// Examples: '' + '/session' = '/session' (root install)
//           '/churchcrm' + '/session' = '/churchcrm/session' (subdirectory install)
$basePath = SlimUtils::getBasePath('/session');

$app = AppFactory::create();
$app->setBasePath($basePath);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware must be added AFTER routing (Slim 4 LIFO: last added = first executed)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

$app->add(new VersionMiddleware());

require __DIR__ . '/routes/password-reset.php';

$app->get('[/]', 'sessionLanding');
$app->map(['GET', 'POST'], '/begin', [StaffSignInPage::class, 'handle']);
$app->get('/end', 'endSession');
$app->get('/two-factor', 'processTwoFactorGet');
$app->post('/two-factor', 'processTwoFactorPost');

/**
 * Doors the install offers a signed-out visitor. Staff is always one of them.
 *
 * @return list<array{id: string, href: string, label: string, primary: bool, icon: string}>
 */
function sessionLandingDoors(): array
{
    $root = SystemURLs::getRootPath();
    $member = SystemConfig::getBooleanValue('bMemberSignIn');
    $register = SystemConfig::getBooleanValue('bEnableSelfRegistration');
    $doors = [];
    if ($member) {
        $doors[] = [
            'id' => 'sessionDoorMember',
            'href' => $root . '/portal/login',
            'label' => gettext('Member Portal'),
            'primary' => true,
            'icon' => 'fa-users',
        ];
    }
    if ($register) {
        $doors[] = [
            'id' => 'sessionDoorRegister',
            'href' => $root . '/portal/register',
            'label' => gettext('Create an account'),
            'primary' => !$member,
            'icon' => 'fa-user-plus',
        ];
    }
    $doors[] = [
        'id' => 'sessionDoorStaff',
        'href' => AuthenticationManager::getStaffSignInURL(),
        'label' => gettext('Staff sign in'),
        'primary' => false,
        'icon' => 'fa-right-to-bracket',
    ];

    return $doors;
}

function sessionLanding(Request $request, Response $response): Response
{
    if (PortalSessionStore::has()) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/portal/')->withStatus(302);
    }
    if (AuthenticationManager::validateUserSessionIsActive(false)) {
        return $response
            ->withHeader('Location', DashboardLandingService::urlFor(AuthenticationManager::getCurrentUser()))
            ->withStatus(302);
    }

    $doors = sessionLandingDoors();
    if (count($doors) === 1) {
        return $response->withHeader('Location', $doors[0]['href'])->withStatus(302);
    }

    $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/session/templates/');

    return $renderer->render($response, 'landing.php', ['doors' => $doors]);
}

function processTwoFactorGet(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/');
    $curUser = AuthenticationManager::getCurrentUser();
    $queryParams = $request->getQueryParams();
    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'user'         => $curUser,
        'bInvalidCode' => isset($queryParams['invalid']),
        'bRecoveryMode' => isset($queryParams['recovery']),
    ];

    return $renderer->render($response, 'two-factor.php', $pageArgs);
}

function processTwoFactorPost(Request $request, Response $response, array $args): void
{
    $loginRequestBody = $request->getParsedBody();
    $queryParams = $request->getQueryParams();
    $isRecoveryMode = isset($queryParams['recovery']);
    $twoFARequest = new LocalTwoFactorTokenRequest($loginRequestBody['TwoFACode'] ?? '', $isRecoveryMode);
    // AuthenticationManager::authenticate() calls RedirectUtils::redirect() which exits.
    // On success: redirects to dashboard. On failure: redirects to /session/two-factor?invalid=1
    AuthenticationManager::authenticate($twoFARequest);
}

function endSession(Request $request, Response $response, array $args): Response
{
    $redirectUrl = AuthenticationManager::signOut();
    $response = RememberedDeviceCookie::without($response->withHeader('Location', $redirectUrl)->withStatus(302));
    $response->getBody()->write('');
    return $response;
}

$app->run();
