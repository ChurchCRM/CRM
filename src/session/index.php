<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Utils\InputUtils;
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

// Add Slim error middleware for proper error handling and logging
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new VersionMiddleware());

require __DIR__ . '/routes/password-reset.php';

$app->get('/begin', 'beginSession');
$app->post('/begin', 'beginSession');
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
    ];

    return $renderer->render($response, 'two-factor.php', $pageArgs);
}

function processTwoFactorPost(Request $request, Response $response, array $args): void
{
    $loginRequestBody = $request->getParsedBody();
    $twoFARequest = new LocalTwoFactorTokenRequest($loginRequestBody['TwoFACode'] ?? '');
    // AuthenticationManager::authenticate() calls RedirectUtils::redirect() which exits.
    // On success: redirects to dashboard. On failure: redirects to /session/two-factor?invalid=1
    AuthenticationManager::authenticate($twoFARequest);
}

function endSession(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::endSession(true);
    
    $redirectUrl = SystemURLs::getRootPath() . '/session/begin';
    $response = $response->withHeader('Location', $redirectUrl)->withStatus(302);
    $response->getBody()->write('');
    return $response;
}

function beginSession(Request $request, Response $response, array $args): Response
{
    $queryParams = $request->getQueryParams();
    $redirectPath = isset($queryParams['location']) ? urldecode($queryParams['location']) : null;
    
    // Check for explicit username in query params (e.g., from password reset)
    $rawUserName = $queryParams['username'] ?? $request->getServerParams()['username'] ?? '';
    $prefilledUserName = InputUtils::sanitizeText($rawUserName);
    
    $pageArgs = [
        'sRootPath'            => SystemURLs::getRootPath(),
        'localAuthNextStepURL' => AuthenticationManager::getSessionBeginURL($redirectPath),
        'forgotPasswordURL'    => AuthenticationManager::getForgotPasswordURL(),
        'prefilledUserName'    => $prefilledUserName,
    ];

    if ($request->getMethod() === 'POST') {
        $loginRequestBody = $request->getParsedBody();
        
        $userPassRequest = new LocalUsernamePasswordRequest(
            $loginRequestBody['User'],
            $loginRequestBody['Password'],
            $redirectPath
        );
        $authenticationResult = AuthenticationManager::authenticate($userPassRequest);
        $pageArgs['sErrorText'] = $authenticationResult->message;
    }

    $renderer = new PhpRenderer('templates/');

    return $renderer->render($response, 'begin-session.php', $pageArgs);
}

$app->run();
