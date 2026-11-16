<?php

use ChurchCRM\Service\CommunityFeedbackService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Any signed-in staff user. No role middleware: the Support menu is on every staff page.
 *
 * @OA\Get(
 *     path="/community-feedback",
 *     summary="Curated community-feedback topics and how many are open",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Open topic count and the topic cards",
 *         @OA\JsonContent(
 *             @OA\Property(property="count", type="integer"),
 *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Not signed in")
 * )
 */
$app->get('/community-feedback', function (Request $request, Response $response): Response {
    return SlimUtils::renderJSON($response, CommunityFeedbackService::getPayload());
});
