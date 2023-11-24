<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/systemupgrade', function (RouteCollectorProxy $group) {
    $group->get('/downloadlatestrelease', function ($request, Response $response, $args) {
        $upgradeFile = ChurchCRMReleaseManager::downloadLatestRelease();

        return SlimUtils::renderJSON($response, $upgradeFile);
    });

    $group->post('/doupgrade', function (Request $request, Response $response, array $args) {
        $input = $request->getParsedBody();
        $upgradeResult = ChurchCRMReleaseManager::doUpgrade($input['fullPath'], $input['sha1']);

        return SlimUtils::renderJSON($response, $upgradeResult);
    });
})->add(AdminRoleAuthMiddleware::class);
