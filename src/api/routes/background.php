<?php

use ChurchCRM\Service\NewDashboardService;
use ChurchCRM\Service\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/background', function (RouteCollectorProxy $group) {
    $group->get('/page', 'getPageCommonData');
    $group->post('/timerjobs', 'runTimerJobsAPI');
});

function getPageCommonData(Request $request, Response $response, array $p_args)
{
    $pageName = $request->getQueryParams()['name'];
    $DashboardValues = NewDashboardService::getValues($pageName);
    $response->getBody()->write(json_encode($DashboardValues));
    return $response->withHeader('Content-Type', 'application/json');
}

function runTimerJobsAPI(Request $request, Response $response, array $p_args)
{
    SystemService::runTimerJobs();
    return $response;
}
