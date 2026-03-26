<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/logs', function (RouteCollectorProxy $group): void {
    // Helper function to locate log files - checks primary logs dir first, falls back to temp if needed
    $getLogsDir = static function (): ?string {
        $docRoot = SystemURLs::getDocumentRoot();
        $primaryLogsDir = $docRoot . '/logs';
        
        // Try primary logs directory first
        if (is_dir($primaryLogsDir) && is_readable($primaryLogsDir)) {
            return $primaryLogsDir;
        }
        
        // Fall back to temp directory if application logs are there
        $tempLogPattern = sys_get_temp_dir() . '/churchcrm-*.log';
        if (!empty(glob($tempLogPattern)) || defined('UNIT_TEST')) {
            // Temp directory has logs or we're in tests, use it
            return sys_get_temp_dir();
        }
        
        // Prefer primary if neither has logs (normal case for empty logs)
        return $primaryLogsDir;
    };
    
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
        $logsDir = SystemURLs::getDocumentRoot() . '/logs';
        $logFiles = glob($logsDir . '/*.log');

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
    $group->get('/{filename}', function (Request $request, Response $response, array $args) use ($getLogsDir): Response {
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

        $logsDir = $getLogsDir();
        if (!is_dir($logsDir) || !is_readable($logsDir)) {
            $response->getBody()->write('Logs directory not accessible');
            return $response->withStatus(500);
        }

        $logPath = $logsDir . '/' . $filename;

        // Check if file exists
        if (!file_exists($logPath) || !is_readable($logPath)) {
            $response->getBody()->write('Log file not found');
            return $response->withStatus(404);
        }

        // Security: Verify the file is inside the logs directory (prevent directory traversal)
        $resolvedPath = realpath($logPath);
        $resolvedDir = realpath($logsDir);
        if (!$resolvedPath || !$resolvedDir || strpos($resolvedPath, $resolvedDir) !== 0) {
            $response->getBody()->write('Invalid file path');
            return $response->withStatus(400);
        }

        $content = file_get_contents($resolvedPath);
        
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

    // Download log file
    $group->get('/{filename}/download', function (Request $request, Response $response, array $args) use ($getLogsDir): Response {
        $filename = $args['filename'] ?? '';

        // Security: Check for path traversal
        if (strpos($filename, '..') !== false) {
            return $response->withStatus(400);
        }

        // Security: Validate filename - must end with .log and contain only safe characters
        if (!preg_match('/^[a-zA-Z0-9\-_\.]+\.log$/', $filename)) {
            return $response->withStatus(400);
        }

        $logsDir = $getLogsDir();
        if (!is_dir($logsDir) || !is_readable($logsDir)) {
            return $response->withStatus(500);
        }

        $logPath = $logsDir . '/' . $filename;

        // Check if file exists and is readable
        if (!file_exists($logPath) || !is_readable($logPath)) {
            return $response->withStatus(404);
        }

        // Security: Verify the file is inside the logs directory (prevent directory traversal)
        $resolvedPath = realpath($logPath);
        $resolvedDir = realpath($logsDir);
        if (!$resolvedPath || !$resolvedDir || strpos($resolvedPath, $resolvedDir) !== 0) {
            return $response->withStatus(400);
        }

        $content = file_get_contents($resolvedPath);

        // Set headers before writing body to avoid middleware or streaming layers
        $response = $response
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($content));

        $response->getBody()->write($content);
        return $response;
    });

    // Delete log file
    $group->delete('/{filename}', function (Request $request, Response $response, array $args) use ($getLogsDir): Response {
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

        $logsDir = $getLogsDir();
        if (!is_dir($logsDir) || !is_writable($logsDir)) {
            $response->getBody()->write('Logs directory not writable');
            return $response->withStatus(500);
        }

        $logPath = $logsDir . '/' . $filename;

        // Check if file exists
        if (!file_exists($logPath)) {
            $response->getBody()->write('Log file not found');
            return $response->withStatus(404);
        }

        // Security: Verify the file is inside the logs directory (prevent directory traversal)
        $resolvedPath = realpath($logPath);
        $resolvedDir = realpath($logsDir);
        if (!$resolvedPath || !$resolvedDir || strpos($resolvedPath, $resolvedDir) !== 0) {
            $response->getBody()->write('Invalid file path');
            return $response->withStatus(400);
        }

        if (unlink($resolvedPath)) {
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write('Error deleting file');
            return $response->withStatus(500);
        }
    });
});
