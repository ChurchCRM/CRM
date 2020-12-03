<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Slim\Http\Response;

$app->group('/systemupgrade', function () {
    $this->get('/downloadlatestrelease', function ($request, Response $response, $args) {
        $upgradeFile = ChurchCRMReleaseManager::downloadLatestRelease();
        return $response->withJson($upgradeFile);
    });

    $this->post('/doupgrade', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $upgradeResult = ChurchCRMReleaseManager::doUpgrade($input->fullPath, $input->sha1);
        return $response->withJson($upgradeResult);
    });
})->add(new AdminRoleAuthMiddleware());
