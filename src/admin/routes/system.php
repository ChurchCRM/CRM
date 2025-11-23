<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/system', function (RouteCollectorProxy $group): void {
    
    $group->get('/reset', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Database Reset Functions'),
        ];
        
        return $renderer->render($response, 'system-reset.php', $pageArgs);
    });

    $group->get('/maintenance', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Maintenance'),
        ];
        
        return $renderer->render($response, 'system-maintenance.php', $pageArgs);
    });

    // System Logs page (moved from v2/admin/logs)
    $group->get('/logs', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $logsDir = SystemURLs::getDocumentRoot() . '/logs';
        $logFiles = [];

        if (is_dir($logsDir)) {
            $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $logFiles[] = [
                        'name' => $file,
                        'path' => $logsDir . '/' . $file,
                        'size' => filesize($logsDir . '/' . $file),
                        'modified' => filemtime($logsDir . '/' . $file),
                    ];
                }
            }
        }

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Logs'),
            'logFiles'   => $logFiles,
        ];

        return $renderer->render($response, 'logs.php', $pageArgs);
    });

});
