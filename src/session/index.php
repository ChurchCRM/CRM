<?php

require '../Include/Config.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require_once __DIR__.'/../vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/session');

require __DIR__.'/../Include/slim/error-handler.php';

$app->addRoutingMiddleware();
$app->add(new VersionMiddleware());
$container = $app->getContainer();

require __DIR__.'/routes/password-reset.php';

$app->get('/begin', 'beginSession');
$app->post('/begin', 'beginSession');
$app->get('/end', 'endSession');
$app->get('/two-factor', 'processTwoFactorGet');
$app->post('/two-factor', 'processTwoFactorPost');

function processTwoFactorGet(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user'      => $curUser,
    ];

    return $renderer->render($response, 'two-factor.php', $pageArgs);
}

function processTwoFactorPost(Request $request, Response $response, array $args)
{
    $loginRequestBody = (object) $request->getParsedBody();
    $request = new LocalTwoFactorTokenRequest($loginRequestBody->TwoFACode);
    AuthenticationManager::authenticate($request);
}

function endSession(Request $request, Response $response, array $args)
{
    AuthenticationManager::endSession();
}

function beginSession(Request $request, Response $response, array $args)
{
    $pageArgs = [
        'sRootPath'            => SystemURLs::getRootPath(),
        'localAuthNextStepURL' => AuthenticationManager::getSessionBeginURL(),
        'forgotPasswordURL'    => AuthenticationManager::getForgotPasswordURL(),
    ];

    if ($request->getMethod() == 'POST') {
        $loginRequestBody = (object) $request->getParsedBody();
        $request = new LocalUsernamePasswordRequest($loginRequestBody->User, $loginRequestBody->Password);
        $authenticationResult = AuthenticationManager::authenticate($request);
        $pageArgs['sErrorText'] = $authenticationResult->message;
    }

    $renderer = new PhpRenderer('templates/');

    $pageArgs['prefilledUserName'] = '';
    // Determine if appropriate to pre-fill the username field
    if (isset($_GET['username'])) {
        $pageArgs['prefilledUserName'] = $_GET['username'];
    } elseif (isset($_SESSION['username'])) {
        $pageArgs['prefilledUserName'] = $_SESSION['username'];
    }

    return $renderer->render($response, 'begin-session.php', $pageArgs);
}

$app->run();
