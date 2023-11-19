<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/systemupgrade', function (RouteCollectorProxy $group) {
    $group->get('/downloadlatestrelease', function ($request, Response $response, $args) {
        $upgradeFile = ChurchCRMReleaseManager::downloadLatestRelease();

        return $response->withJson($upgradeFile);
    });

    $group->post('/doupgrade', function (Request $request, Response $response, array $args) {
        $input = (object) $request->getParsedBody();
        $upgradeResult = ChurchCRMReleaseManager::doUpgrade($input->fullPath, $input->sha1);

        return $response->withJson($upgradeResult);
    });
})->add(AdminRoleAuthMiddleware::class);
