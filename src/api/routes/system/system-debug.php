<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system/debug', function () {
    $this->get('/urls', 'getSystemURLAPI');
});

function getSystemURLAPI(Request $request, Response $response, array $args)
{
    return $response->withJson([
        "RootPath" => SystemURLs::getRootPath(),
        "ImagesRoot" => SystemURLs::getImagesRoot(),
        "DocumentRoot" => SystemURLs::getDocumentRoot(),
        "SupportURL" => SystemURLs::getSupportURL()
    ]);
}
