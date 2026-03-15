<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\ExternalBackup\ExternalBackupPlugin;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Get plugin instance
$plugin = ExternalBackupPlugin::getInstance();
if ($plugin === null) {
    return;
}

// MVC Route - Settings/Status page (admin only)
$app->get('/external-backup/settings', function (Request $request, Response $response) use ($plugin): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'settings.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('External Backup Settings'),
        'status' => $plugin->getStatus(),
        'isConfigured' => $plugin->isConfigured(),
    ]);
})->add(AdminRoleAuthMiddleware::class);

// API Routes (admin only)
$app->group('/external-backup/api', function (RouteCollectorProxy $group) use ($plugin): void {
    // GET /plugins/external-backup/api/status - Get backup status
    $group->get('/status', function (Request $request, Response $response) use ($plugin): Response {
        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => $plugin->getStatus(),
        ]);
    });

    // POST /plugins/external-backup/api/backup - Execute manual remote backup
    $group->post('/backup', function (Request $request, Response $response) use ($plugin): Response {
        $data = $request->getParsedBody();
        $backupType = $data['BackupType'] ?? '3'; // Default to full backup

        $result = $plugin->executeManualBackup($backupType);

        $statusCode = $result['success'] ? 200 : 400;

        return SlimUtils::renderJSON($response, $result, $statusCode);
    });

    // POST /plugins/external-backup/api/test - Test WebDAV connection
    $group->post('/test', function (Request $request, Response $response) use ($plugin): Response {
        $result = $plugin->testConnection();

        $statusCode = $result['success'] ? 200 : 400;

        return SlimUtils::renderJSON($response, $result, $statusCode);
    });
})->add(AdminRoleAuthMiddleware::class);
