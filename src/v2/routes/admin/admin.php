<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/admin', function (RouteCollectorProxy $group): void {
    $group->get('/debug', 'debugPage');
    $group->get('/menus', 'menuPage');
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
