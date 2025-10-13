<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/admin', function (RouteCollectorProxy $group): void {
    $group->get('/debug', 'debugPage');
    $group->get('/menus', 'menuPage');
    $group->get('/database/reset', 'dbResetPage');
    $group->get('/logs', 'logsPage');
    $group->post('/logs/loglevel', 'setLogLevel');
    $group->delete('/logs', 'deleteAllLogs');
    $group->get('/logs/{filename}', 'getLogFileContent');
    $group->delete('/logs/{filename}', 'deleteLogFile');
})->add(AdminRoleAuthMiddleware::class);

function debugPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug'),
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}

function menuPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Custom Menus'),
    ];

    return $renderer->render($response, 'menus.php', $pageArgs);
}

function dbResetPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Database Reset Functions'),
    ];

    return $renderer->render($response, 'database-reset.php', $pageArgs);
}

function logsPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

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
}

function setLogLevel(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $logLevel = $input['value'] ?? null;

    if ($logLevel !== null && is_numeric($logLevel)) {
        // Validate it's a valid Monolog level
        $validLevels = [100, 200, 250, 300, 400, 500, 550, 600];
        if (!in_array((int)$logLevel, $validLevels)) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid log level value']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        SystemConfig::setValue('sLogLevel', $logLevel);
        
        // Reset all logger levels to apply changes immediately
        // Note: This only affects the app logger in the current request
        // New requests will pick up the new level automatically
        try {
            LoggerUtils::resetAppLoggerLevel();
        } catch (\Exception $e) {
            // Logger might not be initialized yet, which is fine
        }
        
        $response->getBody()->write(json_encode(['success' => true, 'value' => $logLevel]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid log level']));
    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
}

function getLogFileContent(Request $request, Response $response, array $args): Response
{
    $filename = $args['filename'];

    // Security: Only allow log files with .log extension and prevent directory traversal
    if (!preg_match('/^[\w\-]+\.log$/', $filename)) {
        $response->getBody()->write('Invalid filename');
        return $response->withStatus(400);
    }

    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $filePath = $logsDir . '/' . $filename;

    // Verify the file exists and is within the logs directory
    if (!file_exists($filePath) || !is_file($filePath) || dirname(realpath($filePath)) !== realpath($logsDir)) {
        $response->getBody()->write('Log file not found');
        return $response->withStatus(404);
    }

    // Read the file content
    $content = file_get_contents($filePath);

    // Return as plain text
    $response->getBody()->write($content);
    return $response->withHeader('Content-Type', 'text/plain');
}

function deleteLogFile(Request $request, Response $response, array $args): Response
{
    $filename = $args['filename'];

    // Security: Only allow log files with .log extension and prevent directory traversal
    if (!preg_match('/^[\w\-]+\.log$/', $filename)) {
        $response->getBody()->write('Invalid filename');
        return $response->withStatus(400);
    }

    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $filePath = $logsDir . '/' . $filename;

    // Verify the file exists and is within the logs directory
    if (!file_exists($filePath) || !is_file($filePath) || dirname(realpath($filePath)) !== realpath($logsDir)) {
        $response->getBody()->write('Log file not found');
        return $response->withStatus(404);
    }

    // Delete the file
    if (unlink($filePath)) {
        $response->getBody()->write('Log file deleted successfully');
        return $response->withStatus(200);
    } else {
        $response->getBody()->write('Failed to delete log file');
        return $response->withStatus(500);
    }
}

function deleteAllLogs(Request $request, Response $response, array $args): Response
{
    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $deletedCount = 0;
    $errors = [];

    if (is_dir($logsDir)) {
        $files = scandir($logsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $filePath = $logsDir . '/' . $file;
                if (is_file($filePath)) {
                    if (unlink($filePath)) {
                        $deletedCount++;
                    } else {
                        $errors[] = $file;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $response->getBody()->write("Deleted $deletedCount log file(s) successfully");
        return $response->withStatus(200);
    } else {
        $response->getBody()->write("Deleted $deletedCount log file(s), but failed to delete: " . implode(', ', $errors));
        return $response->withStatus(207); // 207 Multi-Status
    }
}
