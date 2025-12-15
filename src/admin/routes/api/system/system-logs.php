<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/logs', function (RouteCollectorProxy $group): void {
    // Set log level
    $group->post('/loglevel', function (Request $request, Response $response, array $args): Response {
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
    });

    // Delete all log files
    $group->delete('', function (Request $request, Response $response, array $args): Response {
        $logsDir = __DIR__ . '/../../../../logs/';
        $logFiles = glob($logsDir . '*.log');

        $deletedCount = 0;
        foreach ($logFiles as $logFile) {
            if (unlink($logFile)) {
                $deletedCount++;
            }
        }

        $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deletedCount]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Get log file content
    $group->get('/{filename}', function (Request $request, Response $response, array $args): Response {
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

        $logPath = __DIR__ . '/../../../../logs/' . $filename;

        // Security: Prevent path traversal with realpath check
        $realLogsDir = realpath(__DIR__ . '/../../../../logs/');
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
        
        // Parse log lines and return as JSON array
        // Split by newline and filter empty lines, then reindex array for proper JSON output
        $allLines = explode("\n", $content);
        $lines = [];
        foreach ($allLines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $lines[] = $trimmed;
            }
        }
        
        $response->getBody()->write(json_encode(['success' => true, 'lines' => $lines, 'count' => count($lines)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Delete log file
    $group->delete('/{filename}', function (Request $request, Response $response, array $args): Response {
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

        $logPath = __DIR__ . '/../../../../logs/' . $filename;

        // Security: Prevent path traversal with realpath check
        $realLogsDir = realpath(__DIR__ . '/../../../../logs/');
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
    });
});
