<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
$app->group('/system/debug', function (RouteCollectorProxy $group) {
    $group->get('/urls', 'getSystemURLAPI');
})->add(AdminRoleAuthMiddleware::class);

function getSystemURLAPI(Request $request, Response $response, array $args)
{
    return $response->withJson([
        'RootPath'     => SystemURLs::getRootPath(),
        'ImagesRoot'   => SystemURLs::getImagesRoot(),
        'DocumentRoot' => SystemURLs::getDocumentRoot(),
        'SupportURL'   => SystemURLs::getSupportURL(),
    ]);
}
