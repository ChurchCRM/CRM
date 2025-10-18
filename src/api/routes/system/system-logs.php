<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system', function (RouteCollectorProxy $group): void {
    $group->post('/logs/loglevel', 'setLogsLogLevel');
    $group->delete('/logs', 'deleteAllLogFiles');
    $group->get('/logs/{filename}', 'getLogsFileContent');
    $group->delete('/logs/{filename}', 'deleteLogFile');
})->add(AdminRoleAuthMiddleware::class);

function setLogsLogLevel(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $logLevel = $input['value'] ?? null;

    if (!$logLevel || !is_numeric($logLevel)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid log level']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Set the configuration
    SystemConfig::setValue('sLogLevel', $logLevel);
    
    // Reset app logger level to apply new level immediately
    try {
        LoggerUtils::resetAppLoggerLevel();
    } catch (\Exception $e) {
        // Logger might not be initialized yet, which is fine
    }

    $response->getBody()->write(json_encode(['success' => true, 'level' => $logLevel]));
    return $response->withHeader('Content-Type', 'application/json');
}

function getLogsFileContent(Request $request, Response $response, array $args): Response
{
    $filename = $args['filename'] ?? '';
    
    // Security: Check for path traversal first
    if (strpos($filename, '..') !== false) {
        $response->getBody()->write('Invalid file path');
        return $response->withStatus(400);
    }
    
    // Security: Validate filename - must end with .log and contain only safe characters
    if (!preg_match('/^[a-zA-Z0-9\-_\.]+\.log$/', $filename)) {
        $response->getBody()->write('Invalid filename');
        return $response->withStatus(400);
    }
    
    $logPath = __DIR__ . '/../../../logs/' . $filename;
    
    // Security: Prevent path traversal with realpath check
    $realLogsDir = realpath(__DIR__ . '/../../../logs/');
    if (!$realLogsDir) {
        $response->getBody()->write('Logs directory not found');
        return $response->withStatus(500);
    }
    
    // Check if file exists first
    if (!file_exists($logPath)) {
        $response->getBody()->write('Log file not found');
        return $response->withStatus(404);
    }
    
    // Now check realpath for existing files only
    $realLogPath = realpath($logPath);
    if (!$realLogPath || strpos($realLogPath, $realLogsDir) !== 0) {
        $response->getBody()->write('Invalid file path');
        return $response->withStatus(400);
    }
    
    $content = file_get_contents($realLogPath);
    
    $response->getBody()->write($content);
    return $response->withHeader('Content-Type', 'text/plain');
}

function deleteLogFile(Request $request, Response $response, array $args): Response
{
    $filename = $args['filename'] ?? '';
    
    // Security: Check for path traversal first
    if (strpos($filename, '..') !== false) {
        $response->getBody()->write('Invalid file path');
        return $response->withStatus(400);
    }
    
    // Security: Validate filename - must end with .log and contain only safe characters
    if (!preg_match('/^[a-zA-Z0-9\-_\.]+\.log$/', $filename)) {
        $response->getBody()->write('Invalid filename');
        return $response->withStatus(400);
    }
    
    $logPath = __DIR__ . '/../../../logs/' . $filename;
    
    // Security: Prevent path traversal with realpath check
    $realLogsDir = realpath(__DIR__ . '/../../../logs/');
    if (!$realLogsDir) {
        $response->getBody()->write('Logs directory not found');
        return $response->withStatus(500);
    }
    
    // Check if file exists first
    if (!file_exists($logPath)) {
        $response->getBody()->write('Log file not found');
        return $response->withStatus(404);
    }
    
    // Now check realpath for existing files only
    $realLogPath = realpath($logPath);
    if (!$realLogPath || strpos($realLogPath, $realLogsDir) !== 0) {
        $response->getBody()->write('Invalid file path');
        return $response->withStatus(400);
    }
    
    if (unlink($realLogPath)) {
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write('Error deleting file');
        return $response->withStatus(500);
    }
}

function deleteAllLogFiles(Request $request, Response $response, array $args): Response
{
    $logsDir = __DIR__ . '/../../../logs/';
    $logFiles = glob($logsDir . '*.log');
    
    $deletedCount = 0;
    foreach ($logFiles as $logFile) {
        if (unlink($logFile)) {
            $deletedCount++;
        }
    }
    
    $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deletedCount]));
    return $response->withHeader('Content-Type', 'application/json');
}