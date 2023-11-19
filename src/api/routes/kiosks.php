<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/kiosks', function (RouteCollectorProxy $group) {
    $group->get('/', function (Request $request, Response $response, array $args) {
        $Kiosks = KioskDeviceQuery::create()
            ->joinWithKioskAssignment(Criteria::LEFT_JOIN)
            ->useKioskAssignmentQuery()
            ->joinWithEvent(Criteria::LEFT_JOIN)
            ->endUse()
            ->find();

        return $response->write($Kiosks->toJSON());
    });

    $group->post('/allowRegistration', function (Request $request, Response $response, array $args) {
        $window = new \DateTime();
        $window->add(new \DateInterval('PT05S'));
        SystemConfig::setValue('sKioskVisibilityTimestamp', $window->format('Y-m-d H:i:s'));

        return $response->write(json_encode(['visibleUntil' => $window]));
    });

    $group->post('/{kioskId:[0-9]+}/reloadKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $reload = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->reloadKiosk();

        return $response->write(json_encode($reload, JSON_THROW_ON_ERROR));
    });

    $group->post('/{kioskId:[0-9]+}/identifyKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $identify = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->identifyKiosk();

        return $response->write(json_encode($identify, JSON_THROW_ON_ERROR));
    });

    $group->post('/{kioskId:[0-9]+}/acceptKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $accept = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAccepted(true)
            ->save();

        return $response->write(json_encode($accept, JSON_THROW_ON_ERROR));
    });

    $group->post('/{kioskId:[0-9]+}/setAssignment', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $input = (object) $request->getParsedBody();
        $accept = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAssignment($input->assignmentType, $input->eventId);

        return $response->write(json_encode($accept, JSON_THROW_ON_ERROR));
    });
});
