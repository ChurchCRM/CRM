<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NewDashboardService;

$app->group('/dashboard', function () {
   $this->get('/page', 'getDashboard');
});

function getDashboard(Request $request, Response $response, array $p_args ) {
  $pageName = $request->getQueryParam("currentpagename","");
  $DashboardValues = NewDashboardService::getValues($pageName);
  return $response->withJson($DashboardValues);
}