<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\DashboardService;



$app->group('/dashboard', function () {
   $this->get('/{pagename}', 'getDashboard');
});

/**
 * A method that does the work to handle getting an issue via REST API.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function getDashboard(Request $request, Response $response, array $p_args ) {
  $DashboardValues = DashboardService::getValues($p_args['pagename']);
  return $response->withJson($DashboardValues);
}