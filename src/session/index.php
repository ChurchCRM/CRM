<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
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

// The staff sign-in moved to /admin (member sign-in design §7). A 307 keeps a
// POST's method and body, so a sign-in form cached before the move, or posted
// from a church's own website, still signs in there.
$app->map(['GET', 'POST'], '/begin', function (Request $request, Response $response): Response {
    $query = $request->getUri()->getQuery();

    return $response
        ->withStatus($request->getMethod() === 'POST' ? 307 : 302)
        ->withHeader('Location', AuthenticationManager::getStaffSignInURL() . ($query !== '' ? '?' . $query : ''));
});
$app->get('/end', 'endSession');
$app->get('/two-factor', 'processTwoFactorGet');
$app->post('/two-factor', 'processTwoFactorPost');

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
