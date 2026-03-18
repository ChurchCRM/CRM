<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\VersionUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
            unset($_SESSION['dbUpgradeError']);
        }

        $dbVersion = VersionUtils::getDBVersion();
        $softwareVersion = VersionUtils::getInstalledVersion();

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
