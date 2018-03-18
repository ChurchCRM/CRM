<?php

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Service\SystemService;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NewDashboardService;

$app->group('/background', function () {
    $this->post('/csp-report', 'logCSPReportAPI');
    $this->get('/dashboard/page', 'getDashboardAPI');
    $this->post('/timerjobs', 'runTimerJobsAPI');
});

function logCSPReportAPI(Request $request, Response $response, array $args)
{
    $input = json_decode($request->getBody());
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getAppLogger()->warn($log);
}

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
