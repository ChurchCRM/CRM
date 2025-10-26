<?php

use ChurchCRM\Slim\Controller\PhotoUploadController;
use Slim\Routing\RouteCollectorProxy;

$app->group('/photo', function (RouteCollectorProxy $group): void {
    $group->get('/upload/{type}/{id}', PhotoUploadController::class);
});
