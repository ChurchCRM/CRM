<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/logs', function (RouteCollectorProxy $group): void {
    $group->get('/{filename}', 'getLogFileContent');
})->add(AdminRoleAuthMiddleware::class);

function getLogFileContent(Request $request, Response $response, array $args): Response
{
    $filename = $args['filename'];

    // Security: Only allow log files with .log extension and prevent directory traversal
    if (!preg_match('/^[\w\-]+\.log$/', $filename)) {
        return $response->withStatus(400)->write('Invalid filename');
    }

    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $filePath = $logsDir . '/' . $filename;

    // Verify the file exists and is within the logs directory
    if (!file_exists($filePath) || !is_file($filePath) || dirname(realpath($filePath)) !== realpath($logsDir)) {
        return $response->withStatus(404)->write('Log file not found');
    }

    // Read the file content
    $content = file_get_contents($filePath);

    // Return as plain text
    return $response
        ->withHeader('Content-Type', 'text/plain')
        ->write($content);
}
