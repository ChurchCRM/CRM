<?php

use ChurchCRM\Service\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/background', function (RouteCollectorProxy $group): void {
    $group->post('/timerjobs', 'runTimerJobsAPI');
});

function runTimerJobsAPI(Request $request, Response $response, array $args): Response
{
    SystemService::runTimerJobs();

    return $response;
}
