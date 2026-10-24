<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Service\EmailSignInService;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * The public member sign-in pages under `/portal/login` (member sign-in design
 * §6.1, §7). They need no session, take no API key, and while member sign-in
 * is off every one of them says so instead.
 *
 * A form whose CSRF token has gone stale (the session timed out on the page)
 * goes back to the page it came from with a notice, rather than to the
 * signed-in portal's 403 page.
 */
class PortalSignInPageMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('x-api-key') !== '') {
            throw new HttpForbiddenException($request, gettext('The Member Portal is only available to a signed-in person, not to API clients.'));
        }

        PortalTwig::preparePage();

        if (!EmailSignInService::isEnabled()) {
            return PortalTwig::render(new Response(), 'login/unavailable.html.twig', ['pageTitle' => gettext('Sign in')]);
        }

        try {
            return $handler->handle($request);
        } catch (HttpForbiddenException $e) {
            if ($request->getMethod() !== 'POST') {
                throw $e;
            }
            PortalExtension::addFlash('warning', gettext('That page had been open too long. Please try again.'));

            return (new Response())->withStatus(303)->withHeader('Location', $request->getUri()->getPath());
        }
    }
}
