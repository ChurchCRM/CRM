<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/kiosks', function (RouteCollectorProxy $group): void {
    $group->get('/', function (Request $request, Response $response): Response {
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
                'Failed to retrieve kiosk',
                ['exception' => $e]
            );
        }

        return SlimUtils::renderJSON($response, ['KioskDevices' => $KiosksArray]);
    });

    $group->post('/allowRegistration', function (Request $request, Response $response, array $args): Response {
        $window = new \DateTime();
        $window->add(new \DateInterval('PT05S'));
        SystemConfig::setValue('sKioskVisibilityTimestamp', $window->format('Y-m-d H:i:s'));

        return SlimUtils::renderJSON($response, ['visibleUntil' => $window]);
    });

    $group->post('/{kioskId:[0-9]+}/reloadKiosk', function (Request $request, Response $response, array $args): Response {
        $kioskId = $args['kioskId'];

        KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->reloadKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/{kioskId:[0-9]+}/identifyKiosk', function (Request $request, Response $response, array $args): Response {
        $kioskId = $args['kioskId'];

        KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->identifyKiosk();

        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/{kioskId:[0-9]+}/acceptKiosk', function (Request $request, Response $response, array $args): Response {
        $kioskId = $args['kioskId'];

        KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAccepted(true)
            ->save();

        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/{kioskId:[0-9]+}/setAssignment', function (Request $request, Response $response, array $args): Response {
        $kioskId = $args['kioskId'];
        $input = $request->getParsedBody();

        KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAssignment($input['assignmentType'], $input['eventId']);

        return SlimUtils::renderSuccessJSON($response);
    });
});
