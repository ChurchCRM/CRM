<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalSignInPages;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\PersonSignInService;
use ChurchCRM\Service\RememberedDeviceService;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

// Sign-in and devices (member sign-in design §6.2): the member's remembered
// devices, with a way to sign out one or all of them. Row 8 adds passkeys.

$devicesActor = static function (Request $request): Person {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    if (!$person instanceof Person) {
        throw new HttpNotFoundException($request);
    }

    return $person;
};

$devicesRedirect = static fn (string $path): Response => (new Psr7Response())
    ->withStatus(303)
    ->withHeader('Location', SystemURLs::getRootPath() . $path);

$group->group('/devices', function (RouteCollectorProxy $devices) use ($devicesActor, $devicesRedirect): void {
    $devices->get('', function (Request $request, Response $response) use ($devicesActor): Response {
        $person = $devicesActor($request);

        return PortalTwig::render(
            $response,
            'devices/index.html.twig',
            [
                'pageTitle' => gettext('Sign-in and devices'),
                'devices' => (new RememberedDeviceService())->listForPerson((int) $person->getId(), PortalSessionStore::deviceId()),
                'offerRememberDevice' => PortalSignInPages::offersRememberDevice(),
            ],
            PortalNav::DEVICES
        );
    });

    $devices->post('/{deviceId:[0-9]+}/sign-out', function (Request $request, Response $response, array $args) use ($devicesActor, $devicesRedirect): Response {
        $person = $devicesActor($request);
        $deviceId = (int) $args['deviceId'];
        $isThisDevice = $deviceId === PortalSessionStore::deviceId();
        if (!(new RememberedDeviceService())->signOutDevice((int) $person->getId(), $deviceId)) {
            throw new HttpNotFoundException($request);
        }

        if ($isThisDevice) {
            PortalSessionStore::clear();
            PortalExtension::addFlash('success', gettext('You are signed out on this device.'));

            return RememberedDeviceCookie::without($devicesRedirect('/portal/'));
        }

        PortalExtension::addFlash('success', gettext('That device is signed out.'));

        return $devicesRedirect('/portal/devices');
    });

    $devices->post('/sign-out-everywhere', function (Request $request, Response $response) use ($devicesActor, $devicesRedirect): Response {
        (new PersonSignInService())->signOutEverywhere($devicesActor($request), null);
        PortalSessionStore::clear();
        PortalExtension::addFlash('success', gettext('You are signed out on every device.'));

        return RememberedDeviceCookie::without($devicesRedirect('/portal/'));
    });
})->add(RequirePortalCapability::any(CorePortalCapabilities::DEVICES_MANAGE));
