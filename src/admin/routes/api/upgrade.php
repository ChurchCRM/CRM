<?php

use ChurchCRM\Service\UpgradeAPIService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/upgrade', function (RouteCollectorProxy $group): void {
    /**
     * Download the latest release from GitHub
     * GET /admin/api/upgrade/download-latest-release
     *
     * @return 200 Success with file info (fileName, fullPath, releaseNotes, sha1)
     * @return 400 Error downloading file
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
     * Apply the system upgrade
     * POST /admin/api/upgrade/do-upgrade
     *
     * Request body:
     *   - fullPath (string): Full path to upgrade file
     *   - sha1 (string): SHA1 hash for verification
     *
     * @return 200 Success with empty data
     * @return 500 Error applying upgrade
     */
    $group->post('/do-upgrade', function (Request $request, Response $response, array $args): Response {
        try {
            $input = $request->getParsedBody();
            UpgradeAPIService::doUpgrade($input['fullPath'], $input['sha1']);
            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to apply upgrade'), [], 500, $e, $request);
        }
    });

    /**
     * Refresh upgrade information from GitHub
     * POST /admin/api/upgrade/refresh-upgrade-info
     *
     * Forces a fresh check of available updates from GitHub and updates session state.
     *
     * @return 200 Success with updated session data
     * @return 500 Error refreshing information
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
