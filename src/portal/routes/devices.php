<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\PasskeySite;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalSignInPages;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\PasskeyService;
use ChurchCRM\Service\PersonSignInService;
use ChurchCRM\Service\RememberedDeviceService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

// Sign-in and devices (member sign-in design §6.2, §6.3): the member's
// remembered devices, with a way to sign out one or all of them, and their
// passkeys. After a passkey sign-in, an unconfirmed address can be confirmed
// here (§9).

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
        $principal = PortalContext::principal();

        return PortalTwig::render(
            $response,
            'devices/index.html.twig',
            [
                'pageTitle' => gettext('Sign-in and devices'),
                'devices' => (new RememberedDeviceService())->listForPerson((int) $person->getId(), PortalSessionStore::deviceId()),
                'offerRememberDevice' => PortalSignInPages::offersRememberDevice(),
                'passkeysOn' => PasskeySite::isTurnedOn(),
                'passkeys' => (new PasskeyService())->listForPerson((int) $person->getId()),
                'passkeyAddProblem' => PasskeyService::addProblem($request, $principal),
                'emailToConfirm' => PasskeyService::emailToConfirm($person, $principal),
            ],
            PortalNav::DEVICES
        );
    });

    $devices->post('/passkeys/options', function (Request $request, Response $response) use ($devicesActor): Response {
        $person = $devicesActor($request);
        $problem = PasskeySite::isTurnedOn() ? PasskeyService::addProblem($request, PortalContext::principal()) : gettext('Passkeys are turned off.');
        if ($problem !== null) {
            return SlimUtils::renderJSON($response, ['message' => $problem], 403);
        }

        return SlimUtils::renderJSON($response, ['publicKey' => (new PasskeyService())->addOptions($person)]);
    });

    $devices->post('/passkeys', function (Request $request, Response $response) use ($devicesActor): Response {
        $person = $devicesActor($request);
        if (!PasskeySite::isAvailable($request)) {
            throw new HttpNotFoundException($request);
        }
        $body = (array) $request->getParsedBody();
        $transports = is_array($body['transports'] ?? null) ? array_filter($body['transports'], 'is_string') : [];
        $problem = (new PasskeyService())->add(
            $person,
            PortalContext::principal(),
            (string) PasskeyService::fromBase64Url((string) ($body['clientDataJSON'] ?? '')),
            (string) PasskeyService::fromBase64Url((string) ($body['attestationObject'] ?? '')),
            array_values($transports),
            $request->getHeaderLine('User-Agent'),
        );
        if ($problem !== null) {
            return SlimUtils::renderJSON($response, ['message' => $problem], 400);
        }
        PortalExtension::addFlash('success', gettext('Your passkey was added. Next time, sign in with it on this device.'));

        return SlimUtils::renderJSON($response, ['success' => true]);
    });

    $devices->post('/passkeys/{passkeyId:[0-9]+}/remove', function (Request $request, Response $response, array $args) use ($devicesActor, $devicesRedirect): Response {
        $person = $devicesActor($request);
        if (!PasskeySite::isTurnedOn() || !(new PasskeyService())->remove((int) $person->getId(), (int) $args['passkeyId'])) {
            throw new HttpNotFoundException($request);
        }
        PortalExtension::addFlash('success', gettext('That passkey is removed. It can no longer sign you in.'));

        return $devicesRedirect('/portal/devices');
    });

    $devices->post('/confirm-email', function (Request $request, Response $response) use ($devicesActor, $devicesRedirect): Response {
        $person = $devicesActor($request);
        $address = PasskeyService::emailToConfirm($person, PortalContext::principal());
        if ($address === null) {
            throw new HttpNotFoundException($request);
        }
        if ((new SignInEmailPolicy())->sendConfirmationLink($person)) {
            PortalExtension::addFlash('success', sprintf(gettext('We emailed a link to %s. Use it to confirm your new email address.'), $address));
        } else {
            PortalExtension::addFlash('danger', gettext('We could not send the email. Please try again later, or contact the church office.'));
        }

        return $devicesRedirect('/portal/devices');
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
