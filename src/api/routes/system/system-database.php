<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Backup\BackupDownloader;
use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\RestoreJob;
use ChurchCRM\dto\SystemConfig;
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
        if (SystemConfig::getValue('sExternalBackupUsername') && SystemConfig::getValue('sExternalBackupPassword') && SystemConfig::getValue('sExternalBackupEndpoint')) {
            $input = $request->getParsedBody();
            $BaseName = preg_replace(
                '/[^a-zA-Z0-9\-_]/',
                '',
                SystemConfig::getValue('sChurchName')
            ) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
            $BackupType = $input['BackupType'];
            try {
                $Backup = new BackupJob($BaseName, $BackupType);
                $Backup->execute();
                $copyStatus = $Backup->copyToWebDAV(
                    SystemConfig::getValue('sExternalBackupEndpoint'),
                    SystemConfig::getValue('sExternalBackupUsername'),
                    SystemConfig::getValue('sExternalBackupPassword')
                );

                return SlimUtils::renderJSON($response, ['copyStatus' => $copyStatus]);
            } catch (\Throwable $e) {
                $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
                return SlimUtils::renderErrorJSON($response, gettext('Remote backup failed'), [], $status, $e, $request);
            }
        } else {
            return SlimUtils::renderErrorJSON($response, gettext('WebDAV backups are not correctly configured. Please ensure endpoint, username, and password are set'), [], 500);
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
