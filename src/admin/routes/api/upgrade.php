<?php

use ChurchCRM\Service\UpgradeAPIService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/upgrade', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/api/upgrade/download-latest-release",
     *     operationId="downloadLatestRelease",
     *     summary="Download the latest release from GitHub",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Release file downloaded",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="fileName", type="string"),
     *             @OA\Property(property="fullPath", type="string"),
     *             @OA\Property(property="releaseNotes", type="string"),
     *             @OA\Property(property="sha1", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Error downloading release"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
    $group->get('/download-latest-release', function (Request $request, Response $response, array $args): Response {
        try {
            $upgradeFile = UpgradeAPIService::downloadLatestRelease();
            return SlimUtils::renderJSON($response, $upgradeFile);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to download latest release'), [], 400, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/api/upgrade/do-upgrade",
     *     operationId="doUpgrade",
     *     summary="Apply the system upgrade",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="fullPath", type="string", description="Full path to the upgrade file"),
     *             @OA\Property(property="sha1", type="string", description="SHA1 hash for verification")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Upgrade applied successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=500, description="Error applying upgrade")
     * )
     */
    $group->post('/do-upgrade', function (Request $request, Response $response, array $args): Response {
        try {
            $input = $request->getParsedBody();
            UpgradeAPIService::doUpgrade($input['fullPath'], $input['sha1']);
            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            // Return a localized, user-safe error message and log full details server-side
            // Exception details (including file paths) are logged by SlimUtils::renderErrorJSON
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Failed to apply upgrade. Please check the server logs for details.'),
                [],
                500,
                $e,
                $request
            );
        }
    });

    /**
     * @OA\Post(
     *     path="/api/upgrade/refresh-upgrade-info",
     *     operationId="refreshUpgradeInfo",
     *     summary="Refresh upgrade information from GitHub",
     *     description="Forces a fresh check of available updates from GitHub and updates session state.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Upgrade information refreshed",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=500, description="Error refreshing upgrade information")
     * )
     */
    $group->post('/refresh-upgrade-info', function (Request $request, Response $response, array $args): Response {
        try {
            $updateData = UpgradeAPIService::refreshUpgradeInfo();

            return SlimUtils::renderJSON($response, [
                'data' => $updateData,
                'message' => gettext('Upgrade information refreshed successfully')
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to refresh upgrade information'), [], 500, $e, $request);
        }
    });
});
