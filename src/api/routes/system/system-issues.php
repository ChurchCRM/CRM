<?php

use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Routes

/**
 * @OA\Post(
 *     path="/issues",
 *     summary="Generate a GitHub issue body pre-filled with system diagnostics",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="pageName", type="string"),
 *             @OA\Property(property="screenSize", type="object",
 *                 @OA\Property(property="height", type="integer"),
 *                 @OA\Property(property="width", type="integer")
 *             ),
 *             @OA\Property(property="windowSize", type="object",
 *                 @OA\Property(property="height", type="integer"),
 *                 @OA\Property(property="width", type="integer")
 *             ),
 *             @OA\Property(property="pageSize", type="object",
 *                 @OA\Property(property="height", type="integer"),
 *                 @OA\Property(property="width", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Pre-formatted issue body string for GitHub",
 *         @OA\JsonContent(@OA\Property(property="issueBody", type="string"))
 *     )
 * )
 */
$app->post('/issues', function (Request $request, Response $response, array $args): Response {
    $data = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $issueDescription =
        "Collected Value Title |  Data \r\n" .
        "----------------------|----------------\r\n" .
        'Page Name |' . $data->pageName . "\r\n" .
        'Screen Size |' . $data->screenSize->height . 'x' . $data->screenSize->width . "\r\n" .
        'Window Size |' . $data->windowSize->height . 'x' . $data->windowSize->width . "\r\n" .
        'Page Size |' . $data->pageSize->height . 'x' . $data->pageSize->width . "\r\n" .
        'Platform Information | ' . php_uname($mode = 'a') . "\r\n" .
        'PHP Version | ' . phpversion() . "\r\n" .
        'SQL Version | ' . SystemService::getDBServerVersion() . "\r\n" .
        'ChurchCRM Version |' . $_SESSION['sSoftwareInstalledVersion'] . "\r\n" .
        'Reporting Browser |' . $_SERVER['HTTP_USER_AGENT'] . "\r\n" .
        'Prerequisite Status |' . SystemService::getPrerequisiteStatus() . "\r\n";

    return SlimUtils::renderJSON($response, ['issueBody' => $issueDescription]);
});
