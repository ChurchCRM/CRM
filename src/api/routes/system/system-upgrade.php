<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/systemupgrade', function (RouteCollectorProxy $group) {
    $group->get('/downloadlatestrelease', function ($request, Response $response, $args) {
        $upgradeFile = ChurchCRMReleaseManager::downloadLatestRelease();

        return $response->withJson($upgradeFile);
    });

    $group->post('/doupgrade', function ($request, $response, $args) {
        $input = (object) $request->getParsedBody();
        $upgradeResult = ChurchCRMReleaseManager::doUpgrade($input->fullPath, $input->sha1);

        return $response->withJson($upgradeResult);
    });
})->add(AdminRoleAuthMiddleware::class);
