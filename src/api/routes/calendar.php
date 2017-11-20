<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\CalendarService;

$app->group('/calendar', function () {
    $this->get('/events', 'getEvents');
});


/**
 * A method that does the work to handle getting an issue via REST API.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function getEvents(Request $request, Response $response, array $p_args ) {
    $params = $request->getQueryParams();
    $clanderService = new CalendarService();
    return $response->withJson($clanderService->getEvents($params['start'], $params['end']));
}