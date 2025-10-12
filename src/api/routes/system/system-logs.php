<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/logs', function (RouteCollectorProxy $group): void {
    $group->get('', 'getLogFilesAPI');
    $group->post('/delete', 'deleteLogFilesAPI');
})->add(AdminRoleAuthMiddleware::class);

function getLogFilesAPI(Request $request, Response $response, array $args): Response
{
    $logsPath = SystemURLs::getDocumentRoot() . '/logs/';
    $logFiles = [];

    if (is_dir($logsPath)) {
        $files = scandir($logsPath);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $filePath = $logsPath . $file;
                $logFiles[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                ];
            }
        }
    }

    // Sort by modified time, newest first
    usort($logFiles, fn ($a, $b): int => $b['modified'] <=> $a['modified']);

    return SlimUtils::renderJSON($response, [
        'files' => $logFiles,
        'totalFiles' => count($logFiles),
        'logsPath' => $logsPath,
    ]);
}

function deleteLogFilesAPI(Request $request, Response $response, array $args): Response
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $logsPath = SystemURLs::getDocumentRoot() . '/logs/';
    $deletedFiles = [];
    $errors = [];

    if (isset($input->files) && is_array($input->files)) {
        foreach ($input->files as $file) {
            // Security: Only allow deleting .log files and prevent directory traversal
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'log' || strpos($file, '..') !== false || strpos($file, '/') !== false) {
                $errors[] = 'Invalid file name: ' . $file;
                continue;
            }

            $filePath = $logsPath . $file;
            if (file_exists($filePath) && is_file($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles[] = $file;
                } else {
                    $errors[] = 'Failed to delete: ' . $file;
                }
            } else {
                $errors[] = 'File not found: ' . $file;
            }
        }
    }

    return SlimUtils::renderJSON($response, [
        'success' => count($errors) === 0,
        'deletedFiles' => $deletedFiles,
        'deletedCount' => count($deletedFiles),
        'errors' => $errors,
    ]);
}
