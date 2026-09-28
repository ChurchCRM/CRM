<?php

use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/background', function (RouteCollectorProxy $group): void {
    $group->post('/timerjobs', 'runTimerJobsAPI');
});

/**
 * @OA\Post(
 *     path="/background/timerjobs",
 *     summary="Trigger background timer jobs (page-load fallback scheduler)",
 *     description="Fallback trigger fired from the page footer on every authenticated page load, for installs that cannot run cron. The jobs are rate limited server-side by iTimerJobsMinIntervalMinutes, so a call inside that window returns ran=false without doing any work. The supported scheduler is the command-line runner (cli/timerjobs.php) driven by cron.",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Timer jobs executed, or skipped by the rate limit",
 *         @OA\JsonContent(
 *             @OA\Property(property="ran", type="boolean", example=true, description="False when the rate limit skipped this call"),
 *             @OA\Property(property="lastRun", type="string", nullable=true, example="2026-09-11 08:15:00", description="When the jobs last completed"),
 *             @OA\Property(property="minIntervalMinutes", type="integer", example=15)
 *         )
 *     )
 * )
 */
function runTimerJobsAPI(Request $request, Response $response, array $args): Response
{
    $ran = SystemService::runTimerJobs();
    $lastRun = SystemService::getLastTimerJobsRun();

    return SlimUtils::renderJSON($response, [
        'ran' => $ran,
        'lastRun' => $lastRun?->format('Y-m-d H:i:s'),
        'minIntervalMinutes' => SystemService::getTimerJobsMinIntervalMinutes(),
    ]);
}
