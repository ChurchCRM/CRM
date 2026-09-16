<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * The gate on `/api/portal/*`: a signed-in browser session, and nothing else.
 *
 * It is the API twin of `PortalAccessMiddleware`, and refuses API-key callers
 * for the same reason (design P11): the portal is a session-bound surface, and
 * a key — which names an account but arrives without the session the portal's
 * own pages establish — would be a second route to member data that the
 * portal's rules never see. `AuthMiddleware` already rejects an Edit-Self-only
 * key; this refuses every other one too.
 *
 * Authentication itself is `AuthMiddleware`'s job: by the time this runs, an
 * anonymous request has already been answered with 401.
 */
class PortalApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('x-api-key') !== '') {
            throw new HttpForbiddenException(
                $request,
                gettext('The Member Portal is only available to a signed-in person, not to API clients.')
            );
        }

        if (!AuthenticationManager::getCurrentUser() instanceof User) {
            throw new HttpForbiddenException($request, gettext('No logged in user'));
        }

        return $handler->handle($request);
    }
}
