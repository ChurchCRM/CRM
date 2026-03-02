<?php

use ChurchCRM\Service\DemoDataService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/demo', function (RouteCollectorProxy $group): void {

    /**
     * @OA\Post(
     *     path="/api/demo/load",
     *     summary="Import demo data into the application (Admin role required)",
     *     description="Only available on fresh installations with exactly 1 person, unless the force flag is set.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="includeFinancial", type="boolean", default=false),
     *             @OA\Property(property="includeEvents", type="boolean", default=false),
     *             @OA\Property(property="includeSundaySchool", type="boolean", default=false),
     *             @OA\Property(property="force", type="boolean", default=false, description="Skip the fresh-install guard")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Demo data imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="imported", type="object"),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="elapsedSeconds", type="number")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin role required — or database is not a fresh install"),
     *     @OA\Response(response=500, description="Demo data import failed")
     * )
     */
    $group->post('/load', function (Request $request, Response $response): Response {
        $logger = LoggerUtils::getAppLogger();

        $body = $request->getParsedBody();
        $includeFinancial = isset($body['includeFinancial']) ? (bool)$body['includeFinancial'] : false;
        $includeEvents = isset($body['includeEvents']) ? (bool)$body['includeEvents'] : false;
        $includeSundaySchool = isset($body['includeSundaySchool']) ? (bool)$body['includeSundaySchool'] : false;
        $force = isset($body['force']) ? (bool)$body['force'] : false;

        if (!$force) {
            $peopleCount = PersonQuery::create()->count();
            if ($peopleCount !== 1 ) {
                return SlimUtils::renderJSON($response, [
                    'success' => false,
                    'message' => gettext('Demo data import is only available on fresh installations with exactly 1 person')
                ], 403);
            }
        }


        try {
            $logger->info('Admin demo data import started', ['includeFinancial' => $includeFinancial, 'includeEvents' => $includeEvents, 'includeSundaySchool' => $includeSundaySchool]);
            $demoService = new DemoDataService();
            $result = $demoService->importDemoData($includeFinancial, $includeEvents, $includeSundaySchool);

            $duration = $result['endTime'] - $result['startTime'];

            $responseData = [
                'success' => $result['success'],
                'message' => $result['success'] ? gettext('Demo data loaded successfully') : gettext('Demo data import failed'),
                'imported' => $result['imported'],
                'warnings' => $result['warnings'],
                'errors' => $result['errors'],
                'elapsedSeconds' => isset($duration) ? round($duration, 2) : null
            ];

            // Log final counts for visibility (groups + sunday_schools plus other counters)
            $logger->info('Admin demo data import completed', ['imported' => $result['imported']]);

            // Return 500 status code if the import failed
            $statusCode = $result['success'] ? 200 : 500;

            return SlimUtils::renderJSON($response, $responseData, $statusCode);

        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('An error occurred during demo data import'), [], 500, $e, $request);
        }
    });

});
