<?php

use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/import', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/import/csv/families",
     *     summary="Download a CSV import template for families (Admin role required)",
     *     tags={"Import"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="CSV file attachment"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/csv/families', function (Request $request, Response $response, array $_args): Response {
        $file = __DIR__ . '/../../data/csv-families-template.csv';
        if (!file_exists($file)) {
            return SlimUtils::renderErrorJSON($response, gettext('CSV template not found'), [], 404, null, $request);
        }

        $contents = file_get_contents($file);

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="csv-families-template.csv"');
        $response->getBody()->write($contents);

        return $response;
    });

    /**
     * @OA\Post(
     *     path="/api/import/csv/upload",
     *     summary="Upload and validate a CSV families import file",
     *     tags={"Import"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(@OA\Property(property="csvFile", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Upload accepted"),
     *     @OA\Response(response=400, description="Invalid file"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/csv/upload', function (Request $request, Response $response, array $_args): Response {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['csvFile'])) {
            return SlimUtils::renderErrorJSON($response, gettext('No file uploaded'), [], 400, null, $request);
        }

        $file = $uploadedFiles['csvFile'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return SlimUtils::renderErrorJSON($response, gettext('File upload error'), [], 400, null, $request);
        }

        if (!str_ends_with(strtolower($file->getClientFilename()), '.csv')) {
            return SlimUtils::renderErrorJSON($response, gettext('Only .csv files are accepted'), [], 400, null, $request);
        }

        // TODO: parse and preview CSV rows (issue #8284 follow-on)
        return SlimUtils::renderJSON($response, ['message' => gettext('File uploaded successfully'), 'url' => '']);
    });
})->add(AdminRoleAuthMiddleware::class);
