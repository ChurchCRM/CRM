<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Restricts a route to a browser session signed in with a username and password.
 *
 * AuthMiddleware accepts an `x-api-key` header on every route outside
 * `/api/public`, the MVC modules included, so "only reachable from a page" has
 * to be enforced. Login as Person is one such route: it sets the staff session
 * aside, and an API key has none.
 */
final class SessionOnlyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $isSession = $request->getHeaderLine('x-api-key') === ''
                && AuthenticationManager::getAuthenticationProvider() instanceof LocalAuthentication;
        } catch (Throwable) {
            $isSession = false;
        }

        if (!$isSession) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('This action needs a signed-in browser session.'), [], 403);
        }

        return $handler->handle($request);
    }
}
