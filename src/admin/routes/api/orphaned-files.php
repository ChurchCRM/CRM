<?php

use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/orphaned-files', function (RouteCollectorProxy $group): void {

    /**
     * @OA\Get(
     *     path="/api/orphaned-files",
     *     operationId="getOrphanedFiles",
     *     summary="List orphaned files",
     *     description="Returns files present on disk that are not part of the official ChurchCRM release.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Orphaned file list",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="count", type="integer"),
     *             @OA\Property(property="files", type="array",
     *                 @OA\Items(type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $orphanedFiles = AppIntegrityService::getOrphanedFiles();

        return SlimUtils::renderJSON($response, [
            'count' => count($orphanedFiles),
            'files' => $orphanedFiles,
        ]);
    });

    /**
     * @OA\Post(
     *     path="/api/orphaned-files/delete-all",
     *     operationId="deleteAllOrphanedFiles",
     *     summary="Delete all orphaned files",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Deletion results",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="deleted", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="failed", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=500, description="Error deleting files")
     * )
     */
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
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete orphaned files'), ['deleted' => [], 'failed' => [], 'errors' => []], 500, $e, $request);
        }
    });

});
