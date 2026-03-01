<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/debug', function (RouteCollectorProxy $group): void {
    $group->get('/urls', 'getSystemURLAPI');
})->add(AdminRoleAuthMiddleware::class);

/**
 * @OA\Get(
 *     path="/system/debug/urls",
 *     summary="Get internal system URL paths for debugging (Admin role required)",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="System URL paths",
 *         @OA\JsonContent(
 *             @OA\Property(property="RootPath", type="string"),
 *             @OA\Property(property="ImagesRoot", type="string"),
 *             @OA\Property(property="DocumentRoot", type="string"),
 *             @OA\Property(property="SupportURL", type="string")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getSystemURLAPI(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, [
        'RootPath' => SystemURLs::getRootPath(),
        'ImagesRoot' => SystemURLs::getImagesRoot(),
        'DocumentRoot' => SystemURLs::getDocumentRoot(),
        'SupportURL' => SystemURLs::getSupportURL(),
    ]);
}
