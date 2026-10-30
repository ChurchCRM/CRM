<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\PersonSigninRequest;
use ChurchCRM\Portal\Auth\EmailSignInOutcome;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\PortalSignInPages;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\EmailSignInService;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Member sign-in by email (member sign-in design §6.1, §7). Public: every page
// here works signed out, and none of them says whether an address may sign in.

$renderLoginPage = static function (Response $response, string $template, array $model = []): Response {
    return PortalTwig::render($response, $template, $model + [
        'pageTitle' => gettext('Sign in'),
        'loginUrl' => PortalSignInPages::loginUrl(),
        'staffSignInUrl' => PortalSignInPages::staffSignInUrl(),
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

    return PortalSignInPages::rememberDevice($response, $rememberDevice);
};

$loginForm = static function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    if (PortalSessionStore::has()) {
        return $redirect(PortalSignInPages::landingUrl());
    }

    return $renderLoginPage($response, 'login/request.html.twig', ['email' => '', 'emailError' => '']);
};
$group->get('', $loginForm);
$group->get('/', $loginForm);

$group->post('', function (Request $request, Response $response) use ($renderLoginPage, $redirect): Response {
    $typed = trim((string) ($request->getParsedBody()['email'] ?? ''));
    if (!EmailSignInService::isWellFormedEmail($typed)) {
        return $renderLoginPage($response, 'login/request.html.twig', [
            'email' => $typed,
            'emailError' => gettext('Enter your email address.'),
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
