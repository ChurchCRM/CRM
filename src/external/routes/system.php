<?php

use ChurchCRM\Service\UpgradeService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\VersionUtils;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

 $app->group('/system', function (RouteCollectorProxy $group): void {
    // Shared renderer and page arg builder to avoid duplication between handlers
    $renderer = new PhpRenderer(__DIR__ . '/../templates/');
    $buildPageArgs = function (?string $errorMessage = null, ?string $successMessage = null): array {
        return [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Upgrade'),
            'dbVersion' => VersionUtils::getDBVersion(),
            'softwareVersion' => VersionUtils::getInstalledVersion(),
            'errorMessage' => $errorMessage,
            'successMessage' => $successMessage,
        ];
    };

    $group->get('/db-upgrade', function (Request $request, Response $response, array $args) use ($renderer, $buildPageArgs): Response {
        $pageArgs = $buildPageArgs();
        return $renderer->render($response, 'system-db-update.php', $pageArgs);
    });

    // POST to trigger an upgrade (safer than GET)
    $group->post('/db-upgrade', function (Request $request, Response $response, array $args) use ($renderer, $buildPageArgs): Response {
        $logger = LoggerUtils::getAppLogger();
        $logger->info('POST /external/system/db-upgrade received');
        try {
            $logger->info('Starting database upgrade...');
            UpgradeService::upgradeDatabaseVersion();
            $logger->info('Database upgrade completed successfully');
            // Render the same template with a success message so the user sees progress/outcome.
            $pageArgs = $buildPageArgs(null, gettext('Database upgrade completed successfully. Redirecting to dashboard...'));
            // Render success page (the template will perform a client-side redirect after a short delay)
            return $renderer->render($response, 'system-db-update.php', $pageArgs);
        } catch (\Exception $ex) {
            $logger->error('Database upgrade failed: ' . $ex->getMessage(), ['exception' => $ex]);
            $pageArgs = $buildPageArgs($ex->getMessage());
            return $renderer->render($response, 'system-db-update.php', $pageArgs)->withStatus(500);
        }
    });

});
