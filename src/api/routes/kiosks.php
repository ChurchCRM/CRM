<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Slim\Request\SlimUtils;
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
        return SlimUtils::renderStringJSON($response, $Kiosks->toJSON());
    });

    $group->post('/allowRegistration', function (Request $request, Response $response, array $args) {
        $window = new \DateTime();
        $window->add(new \DateInterval('PT05S'));
        SystemConfig::setValue('sKioskVisibilityTimestamp', $window->format('Y-m-d H:i:s'));

        return SlimUtils::renderJSON($response, ['visibleUntil' => $window]);
    });

    $group->post('/{kioskId:[0-9]+}/reloadKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $reload = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->reloadKiosk();

        return SlimUtils::renderJSON($response, $reload->toArray());
    });

    $group->post('/{kioskId:[0-9]+}/identifyKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $identify = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->identifyKiosk();

        return SlimUtils::renderJSON($response, $identify->toArray());
    });

    $group->post('/{kioskId:[0-9]+}/acceptKiosk', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $accept = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAccepted(true)
            ->save();

        return SlimUtils::renderJSON($response, $accept->toArray());
    });

    $group->post('/{kioskId:[0-9]+}/setAssignment', function (Request $request, Response $response, array $args) {
        $kioskId = $args['kioskId'];
        $input = $request->getParsedBody();
        $accept = KioskDeviceQuery::create()
            ->findOneById($kioskId)
            ->setAssignment($input['assignmentType'], $input['eventId']);

        return SlimUtils::renderJSON($response, $accept->toArray());
    });
});
