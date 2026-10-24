<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\PersonEmailChange;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// /portal/email/confirm/{token}: the link in an email-change message (member
// sign-in design §9). Public, because the link is often opened on another
// device than the one that asked. Opening it changes nothing; the button on
// the page posts back and spends the link.

$renderEmailConfirmPage = static function (Response $response, string $state, ?PersonEmailChange $link, string $token): Response {
    PortalTwig::preparePage();
    $person = $link instanceof PersonEmailChange ? PersonQuery::create()->findPk((int) $link->getPersonId()) : null;

    return PortalTwig::render($response, 'email-change/confirm.html.twig', [
        'pageTitle' => gettext('Confirm your email address'),
        'state' => $state,
        'kind' => $link?->getKind(),
        'email' => $link?->getEmail(),
        'firstName' => $person?->getFirstName(),
        'churchName' => ChurchMetaData::getChurchName(),
        'token' => $token,
    ])->withHeader('Cache-Control', 'no-store');
};

$app->group('/email/confirm', function (RouteCollectorProxy $group) use ($renderEmailConfirmPage): void {
    $group->get('/{token}', function (Request $request, Response $response, array $args) use ($renderEmailConfirmPage): Response {
        $token = (string) $args['token'];
        $link = (new SignInEmailPolicy())->findUsableLink($token);

        return $renderEmailConfirmPage($response, $link instanceof PersonEmailChange ? 'confirm' : 'invalid', $link, $token);
    });

    $group->post('/{token}', function (Request $request, Response $response, array $args) use ($renderEmailConfirmPage): Response {
        $link = (new SignInEmailPolicy())->useLink((string) $args['token']);

        return $renderEmailConfirmPage($response, $link instanceof PersonEmailChange ? 'done' : 'invalid', $link, '');
    });
})->add(new CSRFMiddleware());
