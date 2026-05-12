<?php

use ChurchCRM\dto\SystemConfig;
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
 *             @OA\Property(property="enable", type="boolean",
 *                 description="true = enable telemetry; false = decline for this version")
 *         )
 *     ),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="status", type="string"))
 *     )
 * )
 */
$app->post('/system/telemetry-consent', function (Request $request, Response $response, array $args): Response {
    $data   = json_decode((string) $request->getBody(), true);
    $enable = !empty($data['enable']);

    SystemConfig::setValue('bEnableTelemetry', $enable ? '1' : '0');

    if (!$enable) {
        // Record the version the admin declined so the prompt is suppressed
        // for the remainder of this release and re-shown after the next upgrade.
        SystemConfig::setValue('sTelemetryAskedVersion', VersionUtils::getInstalledVersion());
    }

    return SlimUtils::renderJSON($response, ['status' => 'ok']);
});
