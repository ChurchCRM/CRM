<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * Prepares every Member Portal page for the person the portal session belongs
 * to. `PortalSessionMiddleware` runs first and decides whether there is one.
 *
 * The public theme-asset route is deliberately *not* behind this middleware —
 * a cached page must keep loading its stylesheet after the session has expired.
 */
class PortalAccessMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!PortalContext::principal() instanceof PortalPrincipal) {
            throw new HttpForbiddenException($request, gettext('No logged in user'));
        }

        // Portal pages carry the same security headers as the rest of the
        // application — the CSP nonce every inline script and theme.js uses —
        // and the plugin head/footer content the layout prints.
        PortalTwig::preparePage();

        // Every portal page acts for the session's own person and no other
        // (design P11), so a route reads the actor here and never takes an id
        // from the request.
        return $handler->handle($request->withAttribute(PortalSelfService::ACTOR_ATTRIBUTE, PortalContext::person()));
    }
}
