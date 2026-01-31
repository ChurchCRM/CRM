<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Kiosk API routes - requires authentication and admin role
$app->group('/api', function (RouteCollectorProxy $group): void {
    // Get all kiosk devices
    $group->get('/devices', function (Request $request, Response $response): Response {
        $KiosksArray = [];
        try {
            $Kiosks = KioskDeviceQuery::create()
                ->joinWithKioskAssignment(Criteria::LEFT_JOIN)
                ->useKioskAssignmentQuery()
                ->joinWithEvent(Criteria::LEFT_JOIN)
                ->endUse()
                ->find();
            $KiosksArray = $Kiosks->toArray();
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error(
                'Failed to retrieve kiosks',
                ['exception' => $e]
            );
        }

        return SlimUtils::renderJSON($response, ['KioskDevices' => $KiosksArray]);
    });

    // Enable new kiosk registration window
    $group->post('/allowRegistration', function (Request $request, Response $response): Response {
        $window = new \DateTime();
        $window->add(new \DateInterval('PT30S'));
        SystemConfig::setValue('sKioskVisibilityTimestamp', $window->format('Y-m-d H:i:s'));

        return SlimUtils::renderJSON($response, ['visibleUntil' => $window]);
    });

    // Reload a kiosk
    $group->post('/devices/{kioskId:[0-9]+}/reload', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->reloadKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    // Identify a kiosk
    $group->post('/devices/{kioskId:[0-9]+}/identify', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->identifyKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    // Accept a kiosk
    $group->post('/devices/{kioskId:[0-9]+}/accept', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->setAccepted(true);
        $kiosk->save();

        return SlimUtils::renderSuccessJSON($response);
    });

    // Set kiosk assignment
    $group->post('/devices/{kioskId:[0-9]+}/assignment', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];
        $input = $request->getParsedBody();

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->setAssignment($input['assignmentType'], $input['eventId']);

        return SlimUtils::renderSuccessJSON($response);
    });

    // Delete a kiosk
    $group->delete('/devices/{kioskId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kioskName = $kiosk->getName();

        try {
            // Delete associated assignments first (no cascade in schema)
            KioskAssignmentQuery::create()
                ->filterByKioskId($kioskId)
                ->delete();

            // Then delete the kiosk device
            $kiosk->delete();
            LoggerUtils::getAppLogger()->info('Kiosk deleted', ['kioskId' => $kioskId, 'kioskName' => $kioskName]);

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('Failed to delete kiosk', ['kioskId' => $kioskId, 'exception' => $e->getMessage()]);

            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete kiosk'), [], 500, $e, $request);
        }
    });
})->add(AdminRoleAuthMiddleware::class)->add(AuthMiddleware::class);
