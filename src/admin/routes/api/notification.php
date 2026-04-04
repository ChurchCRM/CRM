<?php

use ChurchCRM\Service\NotificationService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/notification', function (RouteCollectorProxy $group): void {

    /**
     * @OA\Get(
     *     path="/admin/api/notification",
     *     operationId="getAdminNotifications",
     *     summary="Get current in-app system notifications (admin only)",
     *     description="Returns active in-app notifications derived from system state (e.g. update available). Admin role required.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Array of UI notification objects",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="notifications", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="icon", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="message", type="string"),
     *                     @OA\Property(property="link", type="string"),
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="dismissSettingKey", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        return SlimUtils::renderJSON($response, [
            'notifications' => NotificationService::toUiNotifications(NotificationService::getNotifications()),
        ]);
    });
});
