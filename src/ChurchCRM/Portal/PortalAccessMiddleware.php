<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * The gate on every Member Portal page: an authenticated browser session, and
 * nothing else.
 *
 * API-key callers are refused outright. The portal is a rendered, session-bound
 * surface whose every page derives the acting person from the session (decision
 * P11); letting a key in would create a second way to reach member data that
 * does not go through `/api/portal/*`.
 *
 * The public theme-asset route is deliberately *not* behind this middleware —
 * a cached page must keep loading its stylesheet after the session has expired.
 */
class PortalAccessMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('x-api-key') !== '') {
            throw new HttpForbiddenException(
                $request,
                gettext('The Member Portal is only available to a signed-in person, not to API clients.')
            );
        }

        if (!AuthenticationManager::validateUserSessionIsActive(true)) {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', AuthenticationManager::getSessionBeginURL());
        }

        // Portal pages carry the same security headers as the rest of the
        // application — the CSP nonce every inline script and theme.js uses is
        // emitted here.
        require_once rtrim(SystemURLs::getDocumentRoot(), '/\\') . '/Include/Header-Security.php';

        return $handler->handle($request);
    }
}
