<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Post(
 *     path="/system/telemetry-consent",
 *     summary="Record admin consent decision for anonymous telemetry",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="level", type="string",
 *                 enum={"none","errors","warnings","full"},
 *                 description="Telemetry collection level. 'none' declines and suppresses prompt for this version.")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="status", type="string"))
 *     )
 * )
 */
$app->post('/system/telemetry-consent', function (Request $request, Response $response, array $args): Response {
    $data  = json_decode((string) $request->getBody(), true);
    $level = $data['level'] ?? TelemetryService::LEVEL_NONE;

    $validLevels = [
        TelemetryService::LEVEL_NONE,
        TelemetryService::LEVEL_ERRORS,
        TelemetryService::LEVEL_WARNINGS,
        TelemetryService::LEVEL_FULL,
    ];
    if (!in_array($level, $validLevels, true)) {
        $level = TelemetryService::LEVEL_NONE;
    }

    SystemConfig::setValue('sTelemetryLevel', $level);

    if ($level === TelemetryService::LEVEL_NONE) {
        // Record the version the admin declined so the prompt is suppressed
        // for the remainder of this release and re-shown after the next upgrade.
        SystemConfig::setValue('sTelemetryAskedVersion', VersionUtils::getInstalledVersion());
    }

    return SlimUtils::renderJSON($response, ['status' => 'ok']);
})->add(AdminRoleAuthMiddleware::class);
