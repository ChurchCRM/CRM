<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Kiosk API routes - requires authentication and admin role
$app->group('/api', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/kiosk/api/devices",
     *     operationId="getKioskDevices",
     *     summary="List all kiosk devices",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Kiosk device list",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="KioskDevices", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="Id", type="integer"),
     *                     @OA\Property(property="Name", type="string"),
     *                     @OA\Property(property="Accepted", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/kiosk/api/allowRegistration",
     *     operationId="allowKioskRegistration",
     *     summary="Open a 30-second kiosk registration window",
     *     description="Allows a new kiosk device to register itself within the next 30 seconds.",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Registration window opened",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="visibleUntil", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
    $group->post('/allowRegistration', function (Request $request, Response $response): Response {
        $window = new \DateTime();
        $window->add(new \DateInterval('PT30S'));
        SystemConfig::setValue('sKioskVisibilityTimestamp', $window->format('Y-m-d H:i:s'));

        return SlimUtils::renderJSON($response, ['visibleUntil' => $window]);
    });

    /**
     * @OA\Post(
     *     path="/kiosk/api/devices/{kioskId}/reload",
     *     operationId="reloadKiosk",
     *     summary="Reload a kiosk device",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="kioskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Kiosk reload triggered"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=404, description="Kiosk not found")
     * )
     */
    $group->post('/devices/{kioskId:[0-9]+}/reload', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->reloadKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/kiosk/api/devices/{kioskId}/identify",
     *     operationId="identifyKiosk",
     *     summary="Trigger identification signal on a kiosk device",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="kioskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Identification triggered"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=404, description="Kiosk not found")
     * )
     */
    $group->post('/devices/{kioskId:[0-9]+}/identify', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->identifyKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/kiosk/api/devices/{kioskId}/accept",
     *     operationId="acceptKiosk",
     *     summary="Accept a pending kiosk device",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="kioskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Kiosk accepted"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=404, description="Kiosk not found")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/kiosk/api/devices/{kioskId}/assignment",
     *     operationId="setKioskAssignment",
     *     summary="Set kiosk event assignment",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="kioskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="assignmentType", type="integer", description="Assignment type ID"),
     *             @OA\Property(property="eventId", type="integer", nullable=true, description="Event ID to assign (null to unassign)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Assignment updated"),
     *     @OA\Response(response=400, description="Invalid assignment type"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=404, description="Kiosk not found")
     * )
     */
    $group->post('/devices/{kioskId:[0-9]+}/assignment', function (Request $request, Response $response, array $args): Response {
        $kioskId = (int) $args['kioskId'];
        $input = $request->getParsedBody();

        // Validate input parameters
        $assignmentType = InputUtils::filterInt($input['assignmentType'] ?? 0);
        // eventId is optional; when omitted or null it is passed as null to setAssignment()
        // to indicate that the kiosk should not be assigned to a specific event.
        $eventId = (array_key_exists('eventId', $input) && $input['eventId'] !== null && $input['eventId'] !== '')
            ? InputUtils::filterInt($input['eventId'])
            : null;

        if ($assignmentType < 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid assignment type'), [], 400);
        }

        $kiosk = KioskDeviceQuery::create()->findOneById($kioskId);
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk not found'), [], 404);
        }

        $kiosk->setAssignment($assignmentType, $eventId);

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Delete(
     *     path="/kiosk/api/devices/{kioskId}",
     *     operationId="deleteKiosk",
     *     summary="Delete a kiosk device",
     *     tags={"Kiosk"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="kioskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Kiosk deleted"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required"),
     *     @OA\Response(response=404, description="Kiosk not found"),
     *     @OA\Response(response=500, description="Error deleting kiosk")
     * )
     */
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
