<?php

use ChurchCRM\Service\DemoDataService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/demo', function (RouteCollectorProxy $group): void {
    /**
     * GET /api/demo/status
     * Check if demo data can be imported (only 1 admin, system is empty)
     */
    $group->get('/status', function (Request $request, Response $response): Response {
        $adminCount = UserQuery::create()->filterByAdmin(true)->count();
        $peopleCount = PersonQuery::create()->count();
        $familyCount = \ChurchCRM\model\ChurchCRM\FamilyQuery::create()->count();

        return SlimUtils::renderJSON($response, [
            'canImport' => $adminCount === 1 && $peopleCount === 0 && $familyCount === 0,
            'adminCount' => $adminCount,
            'peopleCount' => $peopleCount,
            'familyCount' => $familyCount,
            'reason' => $this->getDenyReason($adminCount, $peopleCount, $familyCount)
        ]);
    });

    /**
     * POST /api/demo/load
     * Load demo data into the system
     * Only available when:
     * - Exactly 1 admin user exists
     * - System is empty (0 people, 0 families)
     */
    $group->post('/load', function (Request $request, Response $response): Response {
        $logger = LoggerUtils::getAppLogger();

        // Validate preconditions
        $adminCount = UserQuery::create()->filterByAdmin(true)->count();
        $peopleCount = PersonQuery::create()->count();
        $familyCount = \ChurchCRM\model\ChurchCRM\FamilyQuery::create()->count();
        if ($adminCount !== 1 || $peopleCount !== 0 || $familyCount !== 0) {
            $logger->warning('Demo data import attempted when conditions not met', [
                'adminCount' => $adminCount,
                'peopleCount' => $peopleCount,
                'familyCount' => $familyCount
            ]);

            return SlimUtils::renderJSON($response, [
                'success' => false,
                'error' => 'Demo data import is only available on fresh installations with exactly 1 admin user',
                'reason' => $this->getDenyReason($adminCount, $peopleCount, $familyCount)
            ], 403);
        }
        $body = $request->getParsedBody();
        $includeFinancial = isset($body['includeFinancial']) ? (bool)$body['includeFinancial'] : false;
        $includeEvents = isset($body['includeEvents']) ? (bool)$body['includeEvents'] : false;

        try {
            $logger->info('Demo data import started', ['includeFinancial' => $includeFinancial, 'includeEvents' => $includeEvents]);
            $demoService = new DemoDataService();
            $result = $demoService->importDemoData($includeFinancial, $includeEvents);

            $duration = $result['endTime'] - $result['startTime'];

            return SlimUtils::renderJSON($response, [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Demo data loaded successfully' : 'Demo data import failed',
                'imported' => $result['imported'],
                'warnings' => $result['warnings'],
                'errors' => $result['errors'],
                'elapsedSeconds' => isset($duration) ? round($duration, 2) : null
            ]);

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

    // Note: Separate financial/events endpoints were removed — use POST /api/demo/load with
    // includeFinancial and includeEvents flags to control optional data seeding.
    // (Events-specific endpoints removed) Events and calendars are importable via
    // POST /api/demo/load using the `includeEvents` flag.
})->add(AdminRoleAuthMiddleware::class);

/**
 * Helper function to determine why demo import is not available
 */
function getDenyReason(int $adminCount, int $peopleCount, int $familyCount): ?string
{
    if ($adminCount !== 1) {
        return "System has {$adminCount} admin users. Demo import requires exactly 1 admin.";
    }

    if ($peopleCount > 1 || $familyCount > 0) {
        return "System already contains data ({$familyCount} families, {$peopleCount} people). Demo import only works on fresh installations.";
    }

    return null;
}
