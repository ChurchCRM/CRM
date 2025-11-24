<?php

use ChurchCRM\Service\DemoDataService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/demo', function (RouteCollectorProxy $group): void {

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
                    'error' => 'Demo data import is only available on fresh installations with exactly 1 person'
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
                'message' => $result['success'] ? 'Demo data loaded successfully' : 'Demo data import failed',
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

        } catch (Exception $e) {
            $logger->error('Demo data import exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return SlimUtils::renderJSON($response, [
                'success' => false,
                'error' => 'An error occurred during demo data import',
                'details' => $e->getMessage()
            ], 500);
        }
    });

});