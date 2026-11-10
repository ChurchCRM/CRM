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
 *     description="Server details (OS, web server, PHP and database versions, installation ID, custom field counts, prerequisites) are included only for admins.",
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
    $rows = [
        'Page Name' => $data->pageName,
        'Screen Size' => $data->screenSize->height . 'x' . $data->screenSize->width,
        'Window Size' => $data->windowSize->height . 'x' . $data->windowSize->width,
        'Page Size' => $data->pageSize->height . 'x' . $data->pageSize->width,
        'ChurchCRM Version' => VersionUtils::getInstalledVersion(),
        'Reporting Browser' => $_SERVER['HTTP_USER_AGENT'] ?? 'API',
        'System Locale' => $localeInfo->getSystemLocale(),
        'User Locale' => $localeInfo->getLocale(),
        'Browser Locale' => $data->browserLocale ?? 'Unknown',
        'Church Country' => ChurchMetaData::getChurchCountry(),
        'System Timezone' => ChurchMetaData::getChurchTimeZone(),
        'Browser Timezone' => $data->browserTimezone ?? 'Unknown',
    ];

    if (AuthenticationManager::getCurrentUser()->isAdmin()) {
        $rows += [
            'Platform Information' => php_uname('a'),
            'Web Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'PHP Version' => phpversion(),
            'SQL Version' => SystemService::getDBServerVersion(),
            'Installation ID' => SystemConfig::getValue('sSystemID'),
            'Person Custom Fields' => PersonCustomMasterQuery::create()->count(),
            'Family Custom Fields' => FamilyCustomMasterQuery::create()->count(),
            'Prerequisite Status' => SystemService::getPrerequisiteStatus(),
        ];
    }

    $issueDescription = "Collected Value Title |  Data \r\n" .
        "----------------------|----------------\r\n";
    foreach ($rows as $title => $value) {
        $issueDescription .= $title . ' |' . $value . "\r\n";
    }

    return SlimUtils::renderJSON($response, ['issueBody' => $issueDescription]);
});
