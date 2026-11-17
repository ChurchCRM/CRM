<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Routes

/**
 * @OA\Post(
 *     path="/issues",
 *     summary="Generate a GitHub issue body pre-filled with system diagnostics",
 *     description="Server details (platform, web server, PHP and SQL versions, installation ID, custom field counts, prerequisite status) are included for administrators only.",
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
 *             ),
 *             @OA\Property(property="browserLocale", type="string"),
 *             @OA\Property(property="browserTimezone", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Pre-formatted issue body string for GitHub",
 *         @OA\JsonContent(@OA\Property(property="issueBody", type="string"))
 *     )
 * )
 */
$app->post('/issues', function (Request $request, Response $response, array $args): Response {
    $data = json_decode($request->getBody(), null, 512);
    $localeInfo = Bootstrapper::getCurrentLocale();
    $serverDetails = '';
    if (AuthenticationManager::getCurrentUser()->isAdmin()) {
        $serverDetails =
            'Platform Information | ' . php_uname('a') . "\r\n" .
            'Web Server |' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\r\n" .
            'PHP Version | ' . phpversion() . "\r\n" .
            'SQL Version | ' . SystemService::getDBServerVersion() . "\r\n" .
            'Installation ID |' . SystemConfig::getValue('sSystemID') . "\r\n" .
            'Person Custom Fields |' . PersonCustomMasterQuery::create()->count() . "\r\n" .
            'Family Custom Fields |' . FamilyCustomMasterQuery::create()->count() . "\r\n" .
            'Prerequisite Status |' . SystemService::getPrerequisiteStatus() . "\r\n";
    }
    $issueDescription =
        "Collected Value Title |  Data \r\n" .
        "----------------------|----------------\r\n" .
        'Page Name |' . $data->pageName . "\r\n" .
        'Screen Size |' . $data->screenSize->height . 'x' . $data->screenSize->width . "\r\n" .
        'Window Size |' . $data->windowSize->height . 'x' . $data->windowSize->width . "\r\n" .
        'Page Size |' . $data->pageSize->height . 'x' . $data->pageSize->width . "\r\n" .
        'ChurchCRM Version |' . VersionUtils::getInstalledVersion() . "\r\n" .
        'Reporting Browser |' . ($_SERVER['HTTP_USER_AGENT'] ?? 'API') . "\r\n" .
        'System Locale |' . $localeInfo->getSystemLocale() . "\r\n" .
        'User Locale |' . $localeInfo->getLocale() . "\r\n" .
        'Browser Locale |' . ($data->browserLocale ?? 'Unknown') . "\r\n" .
        'Church Country |' . ChurchMetaData::getChurchCountry() . "\r\n" .
        'System Timezone |' . ChurchMetaData::getChurchTimeZone() . "\r\n" .
        'Browser Timezone |' . ($data->browserTimezone ?? 'Unknown') . "\r\n" .
        $serverDetails;

    return SlimUtils::renderJSON($response, ['issueBody' => $issueDescription]);
});
