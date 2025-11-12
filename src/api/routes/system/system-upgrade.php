<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/systemupgrade', function (RouteCollectorProxy $group): void {
    $group->get('/download-latest-release', function (Request $request, Response $response, array $args): Response {
        try {
            $upgradeFile = ChurchCRMReleaseManager::downloadLatestRelease();
            return SlimUtils::renderJSON($response, $upgradeFile);
        } catch (\Exception $e) {
            return SlimUtils::renderJSON($response, [
                'message' => $e->getMessage()
            ], 400);
        }
    });

    $group->post('/do-upgrade', function (Request $request, Response $response, array $args): Response {
        try {
            $input = $request->getParsedBody();
            ChurchCRMReleaseManager::doUpgrade($input['fullPath'], $input['sha1']);
            return SlimUtils::renderSuccessJSON($response);
        } catch (\Exception $e) {
            return SlimUtils::renderJSON($response, [
                'message' => $e->getMessage()
            ], 500);
        }
    });

    $group->post('/refresh-upgrade-info', function (Request $request, Response $response, array $args): Response {
        try {
            // Force refresh of upgrade information from GitHub
            ChurchCRMReleaseManager::checkForUpdates();
            
            // Return fresh session data
            return SlimUtils::renderJSON($response, [
                'data' => [
                    'updateAvailable' => $_SESSION['systemUpdateAvailable'] ?? false,
                    'updateVersion' => isset($_SESSION['systemUpdateVersion']) ? $_SESSION['systemUpdateVersion']->__toString() : null
                ],
                'message' => gettext('Upgrade information refreshed successfully')
            ]);
        } catch (\Exception $e) {
            return SlimUtils::renderJSON($response, [
                'message' => $e->getMessage()
            ], 500);
        }
    });
})->add(AdminRoleAuthMiddleware::class);
