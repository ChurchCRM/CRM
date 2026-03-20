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
    $group->get('/csv/families', function (Request $request, Response $response, array $args): Response {
        $file = __DIR__ . '/../../../data/csv-families-template.csv';
        if (!file_exists($file)) {
            return SlimUtils::renderErrorJSON($response, gettext('CSV template not found'), [], 404, null, $request);
        }

        $contents = file_get_contents($file);

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="csv-families-template.csv"');

        $response->getBody()->write($contents);

        return $response;
    });

})->add(AdminRoleAuthMiddleware::class);
