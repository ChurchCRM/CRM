<?php

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — rollout status (#9704).
 *
 * The whole /api/volunteer group is gated by VolunteerV2EnabledMiddleware, so
 * every V2 endpoint added by a later issue inherits the rollout gate by being
 * registered in a route file beside this one.
 */
$app->group('/volunteer', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/volunteer/status",
     *     summary="Report the Volunteer Management rollout state",
     *     tags={"Volunteer"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=403, description="Volunteer Management V2 is not enabled"),
     *     @OA\Response(response=200, description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="version", type="string", enum={"v1","v2","both"}),
     *             @OA\Property(property="v1Enabled", type="boolean"),
     *             @OA\Property(property="v2Enabled", type="boolean")
     *         )
     *     )
     * )
     */
    $group->get('/status', fn (Request $request, Response $response): Response => SlimUtils::renderJSON($response, [
        'version'   => User::getVolunteerVersion(),
        'v1Enabled' => User::isVolunteerV1Enabled(),
        'v2Enabled' => User::isVolunteerV2Enabled(),
    ]));
})->add(new VolunteerV2EnabledMiddleware());
