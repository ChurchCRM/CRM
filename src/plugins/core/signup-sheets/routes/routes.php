<?php

/**
 * Signup Sheets — authenticated routes.
 *
 * Registered by the plugin system only while the plugin is enabled, and mounted
 * on the /plugins Slim app, which already applies AuthMiddleware. Read routes
 * additionally require the Events module (ViewEvents); write routes require the
 * AddEvent permission, matching how church events themselves are gated.
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Plugins\SignupSheets\SignupSheetService;
use ChurchCRM\Plugins\SignupSheets\SignupSheetsPlugin;
use ChurchCRM\Plugins\SignupSheets\SignupValidationException;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ViewEventsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CsvExporter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$signupSheetsPlugin = SignupSheetsPlugin::getInstance();

if ($signupSheetsPlugin === null) {
    return;
}

$signupSheetsViewPath = __DIR__ . '/../views/';

/**
 * Id of the signed-in user, for created_by columns.
 */
$signupSheetsCurrentUserId = static function (): ?int {
    try {
        return (int) AuthenticationManager::getCurrentUser()->getId();
    } catch (\Throwable) {
        return null;
    }
};

// =============================================================================
// Pages
// =============================================================================

$app->group('/signup-sheets', function (RouteCollectorProxy $group) use (
    $signupSheetsPlugin,
    $signupSheetsViewPath
): void {
    // Sheet list
    $group->get('', function (Request $request, Response $response) use (
        $signupSheetsPlugin,
        $signupSheetsViewPath
    ): Response {
        $renderer = new PhpRenderer($signupSheetsViewPath);

        return $renderer->render($response, 'sheet-list.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Signup Sheets'),
            'sheets' => $signupSheetsPlugin->getService()->listSheets(),
            'allowPublic' => $signupSheetsPlugin->isPublicSharingAllowed(),
            'canEdit' => AuthenticationManager::getCurrentUser()->isAddEventEnabled(),
        ]);
    });

    // Sheet editor — new
    $group->get('/new', function (Request $request, Response $response) use (
        $signupSheetsViewPath,
        $signupSheetsPlugin
    ): Response {
        $renderer = new PhpRenderer($signupSheetsViewPath);

        return $renderer->render($response, 'sheet-editor.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('New Signup Sheet'),
            'sheet' => null,
            'events' => EventQuery::create()->orderByStart('desc')->limit(200)->find(),
            'allowPublic' => $signupSheetsPlugin->isPublicSharingAllowed(),
        ]);
    })->add(AddEventsRoleAuthMiddleware::class);

    // Sheet editor — existing
    $group->get('/{sheetId:[0-9]+}/edit', function (Request $request, Response $response, array $args) use (
        $signupSheetsViewPath,
        $signupSheetsPlugin
    ): Response {
        $sheet = $signupSheetsPlugin->getService()->getSheet((int) $args['sheetId']);
        if ($sheet === null) {
            throw new HttpNotFoundException($request, gettext('Signup sheet not found'));
        }

        $renderer = new PhpRenderer($signupSheetsViewPath);

        return $renderer->render($response, 'sheet-editor.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Edit Signup Sheet'),
            'sheet' => $sheet,
            'events' => EventQuery::create()->orderByStart('desc')->limit(200)->find(),
            'allowPublic' => $signupSheetsPlugin->isPublicSharingAllowed(),
        ]);
    })->add(AddEventsRoleAuthMiddleware::class);

    // Sheet detail / roster
    $group->get('/{sheetId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $signupSheetsPlugin,
        $signupSheetsViewPath
    ): Response {
        $service = $signupSheetsPlugin->getService();
        $sheet = $service->getSheet((int) $args['sheetId']);
        if ($sheet === null) {
            throw new HttpNotFoundException($request, gettext('Signup sheet not found'));
        }

        $renderer = new PhpRenderer($signupSheetsViewPath);

        return $renderer->render($response, 'sheet-manage.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => $sheet['shs_title'],
            'sheet' => $sheet,
            'slotsByCategory' => $service->getSlotsByCategory($sheet),
            'claimsBySlot' => $service->getClaimsBySlot((int) $sheet['shs_ID']),
            'isAccepting' => $service->isAcceptingSignups($sheet),
            'allowPublic' => $signupSheetsPlugin->isPublicSharingAllowed(),
            'publicUrl' => empty($sheet['shs_public_token'])
                ? null
                : $service->publicUrl((string) $sheet['shs_public_token']),
            'canEdit' => AuthenticationManager::getCurrentUser()->isAddEventEnabled(),
        ]);
    });

    // Roster CSV
    $group->get('/{sheetId:[0-9]+}/export', function (Request $request, Response $response, array $args) use (
        $signupSheetsPlugin
    ): Response {
        $service = $signupSheetsPlugin->getService();
        $sheet = $service->getSheet((int) $args['sheetId']);
        if ($sheet === null) {
            throw new HttpNotFoundException($request, gettext('Signup sheet not found'));
        }

        $rows = $service->buildRosterCsv((int) $sheet['shs_ID']);
        $headers = array_shift($rows);

        // Volunteer names, emails, phones and comments arrive from an anonymous
        // public form, so the roster must go through CsvExporter — it prepends a
        // tab to values starting =, -, + or @ so a spreadsheet renders them as
        // text rather than evaluating them as formulas.
        $exporter = new CsvExporter();
        $exporter->insertHeaders($headers);
        $exporter->insertRows($rows);

        $filename = 'signup-sheet-' . (int) $sheet['shs_ID'] . '.csv';
        $response->getBody()->write($exporter->getContent());

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    });
})->add(ViewEventsRoleAuthMiddleware::class);

// =============================================================================
// JSON API
// =============================================================================

$app->group('/signup-sheets/api', function (RouteCollectorProxy $group) use (
    $signupSheetsPlugin,
    $signupSheetsCurrentUserId
): void {
    $service = $signupSheetsPlugin->getService();

    // --- Sheets ---------------------------------------------------------

    $group->post('/sheets', function (Request $request, Response $response) use (
        $service,
        $signupSheetsPlugin,
        $signupSheetsCurrentUserId
    ): Response {
        try {
            $body = (array) ($request->getParsedBody() ?? []);
            if (!$signupSheetsPlugin->isPublicSharingAllowed()) {
                $body['isPublic'] = false;
            }
            $sheetId = $service->createSheet($body, $signupSheetsCurrentUserId());

            return SlimUtils::renderJSON($response, ['success' => true, 'sheetId' => $sheetId], 201);
        } catch (SignupValidationException $e) {
            return SlimUtils::renderJSON($response, ['success' => false, 'message' => $e->getMessage()], 400);
        }
    });

    $group->post('/sheets/{sheetId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $service,
        $signupSheetsPlugin
    ): Response {
        try {
            $body = (array) ($request->getParsedBody() ?? []);
            if (!$signupSheetsPlugin->isPublicSharingAllowed()) {
                $body['isPublic'] = false;
            }
            $service->updateSheet((int) $args['sheetId'], $body);

            return SlimUtils::renderSuccessJSON($response);
        } catch (SignupValidationException $e) {
            return SlimUtils::renderJSON($response, ['success' => false, 'message' => $e->getMessage()], 400);
        }
    });

    $group->delete('/sheets/{sheetId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $service
    ): Response {
        $service->deleteSheet((int) $args['sheetId']);

        return SlimUtils::renderSuccessJSON($response);
    });

    // --- Slots ----------------------------------------------------------

    $group->post('/sheets/{sheetId:[0-9]+}/slots', function (Request $request, Response $response, array $args) use (
        $service
    ): Response {
        try {
            $sheet = $service->getSheet((int) $args['sheetId']);
            if ($sheet === null) {
                return SlimUtils::renderJSON($response, ['success' => false, 'message' => gettext('Signup sheet not found')], 404);
            }

            $slotId = $service->createSlot((int) $sheet['shs_ID'], (array) ($request->getParsedBody() ?? []));

            return SlimUtils::renderJSON($response, ['success' => true, 'slotId' => $slotId], 201);
        } catch (SignupValidationException $e) {
            return SlimUtils::renderJSON($response, ['success' => false, 'message' => $e->getMessage()], 400);
        }
    });

    $group->post('/slots/{slotId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $service
    ): Response {
        try {
            $service->updateSlot((int) $args['slotId'], (array) ($request->getParsedBody() ?? []));

            return SlimUtils::renderSuccessJSON($response);
        } catch (SignupValidationException $e) {
            return SlimUtils::renderJSON($response, ['success' => false, 'message' => $e->getMessage()], 400);
        }
    });

    $group->delete('/slots/{slotId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $service
    ): Response {
        $service->deleteSlot((int) $args['slotId']);

        return SlimUtils::renderSuccessJSON($response);
    });

    // --- Claims ---------------------------------------------------------

    // Sign someone up from inside the CRM (staff filling the sheet in for a member).
    $group->post('/sheets/{sheetId:[0-9]+}/claims', function (Request $request, Response $response, array $args) use (
        $service,
        $signupSheetsCurrentUserId
    ): Response {
        try {
            $sheet = $service->getSheet((int) $args['sheetId']);
            if ($sheet === null) {
                return SlimUtils::renderJSON($response, ['success' => false, 'message' => gettext('Signup sheet not found')], 404);
            }

            $body = (array) ($request->getParsedBody() ?? []);
            $slotId = (int) ($body['slotId'] ?? 0);
            $result = $service->claimSlot(
                $sheet,
                $slotId,
                $body,
                SignupSheetService::SOURCE_INTERNAL,
                $signupSheetsCurrentUserId()
            );

            return SlimUtils::renderJSON($response, ['success' => true, 'claimId' => $result['claimId']], 201);
        } catch (SignupValidationException $e) {
            return SlimUtils::renderJSON($response, ['success' => false, 'message' => $e->getMessage()], 400);
        }
    });

    $group->delete('/claims/{claimId:[0-9]+}', function (Request $request, Response $response, array $args) use (
        $service
    ): Response {
        $service->deleteClaim((int) $args['claimId']);

        return SlimUtils::renderSuccessJSON($response);
    });
})->add(AddEventsRoleAuthMiddleware::class);
