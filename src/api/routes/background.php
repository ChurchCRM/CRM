<?php

use ChurchCRM\Service\SystemService;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NewDashboardService;

$app->group('/background', function () {
    $this->get('/dashboard/page', 'getDashboardAPI');
    $this->post('/timerjobs', 'runTimerJobsAPI');
});

function getDashboardAPI(Request $request, Response $response, array $p_args)
{
    $pageName = $request->getQueryParam("currentpagename", "");
    $DashboardValues = NewDashboardService::getValues($pageName);
    return $response->withJson($DashboardValues);
}

function runTimerJobsAPI(Request $request, Response $response, array $args)
{
    SystemService::runTimerJobs();
}
