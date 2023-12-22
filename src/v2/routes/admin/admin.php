<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/admin', function (RouteCollectorProxy $group): void {
    $group->get('/debug', 'debugPage');
    $group->get('/menus', 'menuPage');
    $group->get('/database/reset', 'dbResetPage');
})->add(AdminRoleAuthMiddleware::class);

function debugPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug'),
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}

function menuPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Custom Menus'),
    ];

    return $renderer->render($response, 'menus.php', $pageArgs);
}

function dbResetPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Database Reset Functions'),
    ];

    return $renderer->render($response, 'database-reset.php', $pageArgs);
}
