<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Backup\BackupDownloader;
use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\BackupType;
use ChurchCRM\Backup\RestoreJob;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Plugins\ExternalBackup\ExternalBackupPlugin;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/database', function (RouteCollectorProxy $group): void {

    $group->post('/backup', function (Request $request, Response $response, array $args): Response {
        try {
            $input = $request->getParsedBody();
            $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName')) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
            $BackupType = $input['BackupType'];
            $Backup = new BackupJob($BaseName, $BackupType);
            $Backup->execute();

            return SlimUtils::renderJSON(
                $response,
                json_decode(
                    json_encode(
                        $Backup,
                        JSON_THROW_ON_ERROR
                    ),
                    (bool) JSON_OBJECT_AS_ARRAY,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Backup failed'), [], $status, $e, $request);
        }
    });

    $group->post('/backupRemote', function (Request $request, Response $response, array $args): Response {
        // Check if External Backup plugin is active and configured
        if (!PluginManager::isPluginActive('external-backup')) {
            return SlimUtils::renderErrorJSON($response, gettext('External Backup plugin is not enabled. Please enable and configure it in Plugin Management.'), [], 400);
        }

        /** @var ExternalBackupPlugin|null $plugin */
        $plugin = PluginManager::getPlugin('external-backup');
        if ($plugin === null || !$plugin->isConfigured()) {
            return SlimUtils::renderErrorJSON($response, gettext('External Backup plugin is not configured. Please configure WebDAV settings in the plugin.'), [], 400);
        }

        $input = $request->getParsedBody();
        // Default to full backup (BackupType::FULL_BACKUP = 3)
        $backupType = $input['BackupType'] ?? BackupType::FULL_BACKUP;

        try {
            $result = $plugin->executeManualBackup($backupType);

            return SlimUtils::renderJSON($response, ['copyStatus' => $result]);
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Remote backup failed'), [], $status, $e, $request);
        }
    });

    $group->post('/restore', function (Request $request, Response $response, array $args): Response {
        try {
            $RestoreJob = new RestoreJob();
            $RestoreJob->execute();

            return SlimUtils::renderJSON(
                $response,
                json_decode(
                    json_encode(
                        $RestoreJob,
                        JSON_THROW_ON_ERROR
                    ),
                    (bool) JSON_OBJECT_AS_ARRAY,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
        } catch (\Throwable $e) {
            $logger = LoggerUtils::getAppLogger();
            $logger->error('Restore failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Database restore failed'), [], $status, $e, $request);
        }
    });

    $group->get('/download/{filename}', function (Request $request, Response $response, array $args): Response {
        $filename = $args['filename'];
        try {
            BackupDownloader::downloadBackup($filename);
            return $response;
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Download failed'), [], $status, $e, $request);
        }
    });
})->add(AdminRoleAuthMiddleware::class);
