<?php

use ChurchCRM\Service\NewDashboardService;
use ChurchCRM\Service\SystemService;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/background', function () {
    $this->get('/page', 'getPageCommonData');
    $this->post('/timerjobs', 'runTimerJobsAPI');
});

function getPageCommonData(Request $request, Response $response, array $p_args)
{
    $pageName = $request->getQueryParam("name", "");
    $DashboardValues = NewDashboardService::getValues($pageName);
    return $response->withJson($DashboardValues);
}

function runTimerJobsAPI(Request $request, Response $response, array $args)
{
    SystemService::runTimerJobs();
}
