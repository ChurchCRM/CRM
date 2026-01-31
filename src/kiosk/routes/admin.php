<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Admin routes group - requires authentication and admin role
$app->group('/admin', function (RouteCollectorProxy $group): void {
    // Kiosk Manager Dashboard
    $group->get('', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Kiosk Manager'),
        ];

        return $renderer->render($response, 'manager.php', $pageArgs);
    });

    $group->get('/', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Kiosk Manager'),
        ];

        return $renderer->render($response, 'manager.php', $pageArgs);
    });
})->add(AdminRoleAuthMiddleware::class)->add(AuthMiddleware::class);
