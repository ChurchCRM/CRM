<?php

namespace ChurchCRM\Portal;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * The API twin of `PortalAccessMiddleware`: puts the portal session's person
 * on every `/api/portal/*` request. `PortalSessionMiddleware` runs first; it
 * refuses API keys and decides whether there is a portal session at all, so no
 * route here takes a person id from the caller (design P11).
 */
class PortalApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!PortalContext::principal() instanceof PortalPrincipal) {
            throw new HttpForbiddenException($request, gettext('No logged in user'));
        }

        $person = PortalContext::person();
        if (!$person instanceof Person) {
            throw new HttpForbiddenException(
                $request,
                gettext('Your account is not linked to a person record. Please contact the church office.')
            );
        }

        return $handler->handle($request->withAttribute(PortalSelfService::ACTOR_ATTRIBUTE, $person));
    }
}
