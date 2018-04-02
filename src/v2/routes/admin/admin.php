<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Role\AdminRoleAuthMiddleware;

$app->group('/admin', function () {
    $this->get('/debug', 'debugPage');
})->add(new AdminRoleAuthMiddleware());

function debugPage(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug')
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}


