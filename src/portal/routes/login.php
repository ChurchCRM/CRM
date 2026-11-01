<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\PersonSigninRequest;
use ChurchCRM\Portal\Auth\EmailSignInOutcome;
use ChurchCRM\Portal\Auth\PasskeySignInOutcome;
use ChurchCRM\Portal\Auth\PasskeySite;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\PortalSignInPages;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\EmailSignInService;
use ChurchCRM\Service\PasskeyService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Member sign-in by email or passkey (member sign-in design §6.1, §6.3, §7).
// Public: every page here works signed out, and none of them says whether an
// address may sign in.

$renderLoginPage = static function (Response $response, string $template, array $model = []): Response {
    return PortalTwig::render($response, $template, $model + [
        'pageTitle' => gettext('Sign in'),
        'loginUrl' => PortalSignInPages::loginUrl(),
        'offerRememberDevice' => PortalSignInPages::offersRememberDevice(),
    ]);
};

$redirect = static fn (string $url): Response => (new Psr7Response())->withStatus(303)->withHeader('Location', $url);

$finishSignIn = static function (Request $request, Response $response, EmailSignInOutcome $outcome) use ($renderLoginPage, $redirect): Response {
    if ($outcome === EmailSignInOutcome::REFUSED) {
        return $renderLoginPage($response, 'login/failed.html.twig');
    }

    $rememberDevice = PortalSignInPages::offersRememberDevice() && ($request->getParsedBody()['rememberDevice'] ?? '') === '1';
    $response = PortalSignInPages::withoutBrowserCookie($redirect(PortalSignInPages::landingUrl()));

    return PortalSignInPages::rememberDevice($request, $response, $rememberDevice);
};

// A remembered device goes on to the portal, which signs it in or sends it back here without its cookie.
$loginForm = static function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    // A browser holding a staff session sees the form: signing in here replaces it (design §6.4).
    if (PortalSessionStore::load()?->method->replacesStaffSession() || RememberedDeviceCookie::isPresent($request)) {
        return $redirect(PortalSignInPages::landingUrl());
    }

    return $renderLoginPage($response, 'login/request.html.twig', [
        'email' => '',
        'emailError' => '',
        'passkeySignIn' => PasskeySite::isAvailable($request),
    ]);
};
$group->get('', $loginForm);
$group->get('/', $loginForm);

$group->post('', function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    $typed = trim((string) ($request->getParsedBody()['email'] ?? ''));
    if (!EmailSignInService::isWellFormedEmail($typed)) {
        return $renderLoginPage($response, 'login/request.html.twig', [
            'email' => $typed,
            'emailError' => gettext('Enter your email address.'),
            'passkeySignIn' => PasskeySite::isAvailable($request),
        ]);
    }

    $browserSecret = EmailSignInService::newBrowserSecret();
    (new EmailSignInService())->request($typed, $browserSecret);

    return PortalSignInPages::withBrowserCookie($redirect(PortalSignInPages::loginUrl('/code')), $browserSecret);
});

$group->get('/code', function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    if (PortalSignInPages::browserSecret($request) === '') {
        return $redirect(PortalSignInPages::loginUrl());
    }

    return $renderLoginPage($response, 'login/code.html.twig', ['codeError' => '']);
});

$group->post('/code', function (Request $request, Response $response) use ($renderLoginPage, $finishSignIn): Response {
    $code = (string) ($request->getParsedBody()['code'] ?? '');
    $outcome = (new EmailSignInService())->signInWithCode($code, PortalSignInPages::browserSecret($request));
    if ($outcome === EmailSignInOutcome::CODE_NOT_ACCEPTED) {
        return $renderLoginPage($response, 'login/code.html.twig', [
            'codeError' => gettext('That code did not work. Check the newest email from us, or ask for a new one.'),
        ]);
    }

    return $finishSignIn($request, $response, $outcome);
});

$group->get('/confirm/{token:[0-9a-f]{64}}', function (Request $request, Response $response, array $args) use ($renderLoginPage): Response {
    $signInRequest = (new EmailSignInService())->findLinkRequest($args['token']);
    if (!$signInRequest instanceof PersonSigninRequest) {
        return $renderLoginPage($response, 'login/link-expired.html.twig');
    }

    return $renderLoginPage($response, 'login/confirm.html.twig', [
        'firstName' => (string) $signInRequest->getPerson()->getFirstName(),
        'churchName' => ChurchMetaData::getChurchName(),
        'confirmUrl' => PortalSignInPages::loginUrl('/confirm/' . $args['token']),
    ]);
});

$group->post('/confirm/{token:[0-9a-f]{64}}', function (Request $request, Response $response, array $args) use ($renderLoginPage, $finishSignIn): Response {
    $outcome = (new EmailSignInService())->signInWithLink($args['token']);
    if ($outcome === EmailSignInOutcome::LINK_UNAVAILABLE) {
        return $renderLoginPage($response, 'login/link-expired.html.twig');
    }

    return $finishSignIn($request, $response, $outcome);
});

$group->post('/passkey/options', function (Request $request, Response $response): Response {
    if (!PasskeySite::isAvailable($request)) {
        return SlimUtils::renderJSON($response, ['message' => gettext('Passkeys cannot be used here.')], 404);
    }

    return SlimUtils::renderJSON($response, ['publicKey' => (new PasskeyService())->signInOptions()]);
});

$group->post('/passkey', function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    if (!PasskeySite::isAvailable($request)) {
        return $redirect(PortalSignInPages::loginUrl());
    }

    $body = (array) $request->getParsedBody();
    $binary = static fn (string $name): string => (string) PasskeyService::fromBase64Url((string) ($body[$name] ?? ''));
    $outcome = (new PasskeyService())->signIn(
        $binary('credentialId'),
        $binary('clientDataJSON'),
        $binary('authenticatorData'),
        $binary('signature'),
        $binary('userHandle'),
    );
    if ($outcome === PasskeySignInOutcome::EXPIRED) {
        PortalExtension::addFlash('warning', gettext('That took too long. Please try again.'));

        return $redirect(PortalSignInPages::loginUrl());
    }
    if ($outcome === PasskeySignInOutcome::FAILED) {
        return $renderLoginPage($response, 'login/failed.html.twig', ['passkey' => true]);
    }

    $response = PortalSignInPages::withoutBrowserCookie($redirect(PortalSignInPages::landingUrl()));

    return PortalSignInPages::rememberDevice($request, $response, false);
});
