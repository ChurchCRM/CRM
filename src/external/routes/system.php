<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\VersionUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// The interim "limited access" landing is retired: a self-service login now
// lands in the Member Portal (#9863, design §2.2), and MP8 (#9869) deleted the
// template this route used to render. The route itself stays — permanently — so
// links in old emails and bookmarks keep working.
$app->get('/limited-access', function (Request $request, Response $response): Response {
    return $response
        ->withStatus(302)
        ->withHeader('Location', SystemURLs::getRootPath() . '/portal/');
});

 $app->group('/system', function (RouteCollectorProxy $group): void {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/');

    $group->get('/db-upgrade', function (Request $request, Response $response, array $args) use ($renderer): Response {
        // Check for auto-upgrade error stored in session by Bootstrapper
        $errorMessage = null;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['dbUpgradeError'])) {
            $errorMessage = $_SESSION['dbUpgradeError'];
            // Preserve the failure until an administrator repairs it; avoid automatic retry loops.
            unset($_SESSION['dbUpgradePending']);
        }

        $dbVersion = VersionUtils::getDBVersion();
        $softwareVersion = VersionUtils::getInstalledVersion();

        if (empty($errorMessage) && !empty($_SESSION['dbUpgradePending']) && version_compare($softwareVersion, $dbVersion, '>')) {
            $response = $response->withHeader('Cache-Control', 'no-store')
                ->withHeader('Refresh', '1; url=' . SystemURLs::getRootPath() . '/external/system/db-upgrade');
            return $renderer->render($response, 'system-db-progress.php', ['sRootPath' => SystemURLs::getRootPath()]);
        }
        if (empty($errorMessage) && !empty($_SESSION['dbUpgradePending']) && $softwareVersion === $dbVersion) {
            unset($_SESSION['dbUpgradePending']);
            return $response->withHeader('Location', SystemURLs::getRootPath() . '/session/end')->withStatus(303);
        }

        // Issue 3 fix: if versions match and no upgrade error, there is nothing to show here.
        // Redirect to root so a direct visit to this URL is not misleading.
        if (empty($errorMessage) && version_compare($softwareVersion, $dbVersion, '>=')) {
            return $response->withHeader('Location', SystemURLs::getRootPath() . '/')->withStatus(302);
        }

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Version Mismatch'),
            'dbVersion' => $dbVersion,
            'softwareVersion' => $softwareVersion,
            'errorMessage' => $errorMessage,
        ];
        return $renderer->render($response, 'system-db-update.php', $pageArgs);
    });
});
