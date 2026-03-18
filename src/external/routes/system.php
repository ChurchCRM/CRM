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

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Version Mismatch'),
            'dbVersion' => VersionUtils::getDBVersion(),
            'softwareVersion' => VersionUtils::getInstalledVersion(),
            'errorMessage' => $errorMessage,
        ];
        return $renderer->render($response, 'system-db-update.php', $pageArgs);
    });
});
