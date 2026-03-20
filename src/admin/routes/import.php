<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/import', function (RouteCollectorProxy $group): void {
    $group->get('/csv', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sPageTitle' => gettext('Import from Spreadsheet'),
        ];

        return $renderer->render($response, 'csv-import.php', $pageArgs);
    });
})->add(AdminRoleAuthMiddleware::class);
