<?php

use ChurchCRM\Service\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/background', function (RouteCollectorProxy $group): void {
    $group->post('/timerjobs', 'runTimerJobsAPI');
});

/**
 * @OA\Post(
 *     path="/background/timerjobs",
 *     summary="Trigger background timer jobs (scheduled task runner)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Timer jobs executed successfully")
 * )
 */
function runTimerJobsAPI(Request $request, Response $response, array $args): Response
{
    SystemService::runTimerJobs();

    return $response;
}
