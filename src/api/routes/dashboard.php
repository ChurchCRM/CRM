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
  echo $DashboardRenderCode;
  //return $response->withBody($DashboardRenderCode);
}
/**
 * A method that does the work to handle getting an issue via REST API.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function getDashboard(Request $request, Response $response, array $p_args ) {
  $pageName = $request->getQueryParam("currentpagename","");
  $DashboardValues = NewDashboardService::getValues($pageName);
  return $response->withJson($DashboardValues);
}