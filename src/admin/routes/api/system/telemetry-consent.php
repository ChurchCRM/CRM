<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Post(
 *     path="/admin/api/system/telemetry-consent",
 *     summary="Record admin consent decision for anonymous telemetry",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="level", type="string",
 *                 enum={"none","install","errors","full"},
 *                 description="Telemetry collection level. Every choice dismisses the one-time consent prompt.")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="status", type="string"))
 *     )
 * )
 */
$app->post('/api/system/telemetry-consent', function (Request $request, Response $response, array $args): Response {
    $data  = json_decode((string) $request->getBody(), true);
    $level = $data['level'] ?? TelemetryService::LEVEL_NONE;

    $validLevels = [
        TelemetryService::LEVEL_NONE,
        TelemetryService::LEVEL_ERRORS,
        TelemetryService::LEVEL_INSTALL,
        TelemetryService::LEVEL_FULL,
    ];
    if (!in_array($level, $validLevels, true)) {
        $level = TelemetryService::LEVEL_NONE;
    }

    SystemConfig::setValue('sTelemetryLevel', $level);

    SystemConfig::setValue('sTelemetryAskedVersion', VersionUtils::getInstalledVersion());
    if ($level !== TelemetryService::LEVEL_NONE) {
        TelemetryService::syncLifecycle();
    }

    return SlimUtils::renderJSON($response, ['status' => 'ok']);
});
