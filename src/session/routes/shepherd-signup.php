<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Shepherd\SignupService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/signup', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        return (new PhpRenderer(__DIR__ . '/../templates/'))->render($response, 'shepherd-signup.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'mode' => 'request',
        ]);
    });

    $group->post('', function (Request $request, Response $response): Response {
        (new SignupService())->submit((array) $request->getParsedBody(), shepherdClientIp($request));
        return (new PhpRenderer(__DIR__ . '/../templates/'))->render($response, 'shepherd-signup.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'mode' => 'submitted',
            'message' => SignupService::GENERIC_SUBMISSION_MESSAGE,
        ]);
    })->add(new CSRFMiddleware('shepherd_signup'));

    $group->get('/verify', function (Request $request, Response $response): Response {
        $valid = (new SignupService())->verify((string) ($request->getQueryParams()['token'] ?? ''), shepherdClientIp($request));
        return (new PhpRenderer(__DIR__ . '/../templates/'))->render($response, 'shepherd-signup.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'mode' => 'status',
            'success' => $valid,
            'message' => $valid
                ? 'Your email is verified. An administrator will review your request; you do not have access yet.'
                : 'This verification link is invalid or has expired.',
        ]);
    });

    $group->get('/password/{token}', function (Request $request, Response $response, array $args): Response {
        $token = (string) $args['token'];
        $valid = (new SignupService())->getPasswordRequest($token) !== null;
        return (new PhpRenderer(__DIR__ . '/../templates/'))->render($response, 'shepherd-signup.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'mode' => $valid ? 'password' : 'status',
            'token' => $token,
            'success' => false,
            'message' => $valid ? '' : 'This password setup link is invalid or has expired.',
        ]);
    });

    $group->post('/password/{token}', function (Request $request, Response $response, array $args): Response {
        $body = (array) $request->getParsedBody();
        $success = false;
        $message = 'The password could not be set. Check that both entries match and the link is still valid.';
        try {
            $success = (new SignupService())->setPassword(
                (string) $args['token'],
                (string) ($body['password'] ?? ''),
                (string) ($body['password_confirmation'] ?? ''),
                shepherdClientIp($request)
            );
            if ($success) {
                $message = 'Your password is set. You may now sign in.';
            }
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }
        return (new PhpRenderer(__DIR__ . '/../templates/'))->render($response, 'shepherd-signup.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'mode' => $success ? 'status' : 'password',
            'token' => (string) $args['token'],
            'success' => $success,
            'message' => $message,
        ]);
    })->add(new CSRFMiddleware('shepherd_password_setup'));
});

function shepherdClientIp(Request $request): string
{
    $server = $request->getServerParams();
    return (string) ($server['HTTP_X_SHEPHERD_CLIENT_IP'] ?? $server['REMOTE_ADDR'] ?? 'unknown');
}
