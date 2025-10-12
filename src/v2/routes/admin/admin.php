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
    $group->get('/logs', 'logsPage');
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

function logsPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $logFiles = [];

    if (is_dir($logsDir)) {
        $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $logFiles[] = [
                    'name' => $file,
                    'path' => $logsDir . '/' . $file,
                    'size' => filesize($logsDir . '/' . $file),
                    'modified' => filemtime($logsDir . '/' . $file),
                ];
            }
        }
    }

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('System Logs'),
        'logFiles'   => $logFiles,
    ];

    return $renderer->render($response, 'logs.php', $pageArgs);
}
