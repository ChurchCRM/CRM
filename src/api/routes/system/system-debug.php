<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system/debug', function () {
    $this->get('/urls', 'getSystemURLAPI');
})->add(new AdminRoleAuthMiddleware());

function getSystemURLAPI(Request $request, Response $response, array $args)
{
    return $response->withJson([
        "RootPath" => SystemURLs::getRootPath(),
        "ImagesRoot" => SystemURLs::getImagesRoot(),
        "DocumentRoot" => SystemURLs::getDocumentRoot(),
        "SupportURL" => SystemURLs::getSupportURL()
    ]);
}
