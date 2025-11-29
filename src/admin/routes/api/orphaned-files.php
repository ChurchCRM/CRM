<?php

use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/orphaned-files', function (RouteCollectorProxy $group): void {

    // Get list of orphaned files
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $orphanedFiles = AppIntegrityService::getOrphanedFiles();

        return SlimUtils::renderJSON($response, [
            'count' => count($orphanedFiles),
            'files' => $orphanedFiles,
        ]);
    });

    // Delete all orphaned files
    $group->post('/delete-all', function (Request $request, Response $response, array $args): Response {
        try {
            $result = AppIntegrityService::deleteOrphanedFiles();

            return SlimUtils::renderJSON($response, [
                'success' => true,
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
                'message' => sprintf(
                    gettext('Deleted %d files, %d failed'),
                    count($result['deleted']),
                    count($result['failed'])
                ),
            ]);
        } catch (\Exception $e) {
            return SlimUtils::renderJSON($response, [
                'success' => false,
                'message' => $e->getMessage(),
                'deleted' => [],
                'failed' => [],
                'errors' => [$e->getMessage()],
            ], 500);
        }
    });

});
