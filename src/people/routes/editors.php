<?php

use ChurchCRM\Controller\PeopleRecordController;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Slim\Routing\RouteCollectorProxy;

$app->group('', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/person/editor', [PeopleRecordController::class, 'person']);
    $group->map(['GET', 'POST'], '/family/editor', [PeopleRecordController::class, 'family']);
})->add(new CSRFMiddleware());
