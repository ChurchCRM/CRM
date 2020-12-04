<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/admin', function () {
    $this->get('/debug', 'debugPage');
    $this->get('/menus', 'menuPage');
    $this->get('/database/reset', 'dbResetPage');
})->add(new AdminRoleAuthMiddleware());

function debugPage(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug')
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}

function menuPage(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Custom Menus')
    ];

    return $renderer->render($response, 'menus.php', $pageArgs);
}


function dbResetPage(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Database Reset Functions')
    ];

    return $renderer->render($response, 'database-reset.php', $pageArgs);
}


