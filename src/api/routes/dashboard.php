<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NewDashboardService;

$app->group('/dashboard', function () {
   $this->get('/page', 'getDashboard');
   $this->get('/renderer', 'getDashboardRenderer');
});

function getDashboardRenderer(Request $request, Response $response, array $p_args ) {
  $pageName = $request->getQueryParam("currentpagename","");
  $DashboardRenderCode = NewDashboardService::getRenderCode($pageName);
  return $response->getBody()->write($DashboardRenderCode);
}

function getDashboard(Request $request, Response $response, array $p_args ) {
  $pageName = $request->getQueryParam("currentpagename","");
  $DashboardValues = NewDashboardService::getValues($pageName);
  return $response->withJson($DashboardValues);
}