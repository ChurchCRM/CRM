<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Slim\Http\Response;

$app->group('/systemupgrade', function () {
    $this->get('/downloadlatestrelease', function ($request, Response $response, $args) {
        $upgradeFile = $this->SystemService->downloadLatestRelease();
       return $response->withJson($upgradeFile);
    });

    $this->post('/doupgrade', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $upgradeResult = $this->SystemService->doUpgrade($input->fullPath, $input->sha1);
        return $response->withJson($upgradeResult);
    });
})->add(new AdminRoleAuthMiddleware());
