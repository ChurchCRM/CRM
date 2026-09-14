<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Restricts a route to browser sessions established by a real login.
 *
 * AuthMiddleware happily authenticates an `x-api-key` header on *any* route
 * outside `/api/public`, including the MVC modules, so "this route is only
 * reachable from a page" is not true by default — it has to be enforced. Add
 * this middleware to routes whose effect is meaningless or dangerous for a
 * token-bearing client, such as starting or ending an admin masquerade
 * (issue #9843).
 *
 * Both halves are checked: an explicit `x-api-key` header on the request, and
 * an active authentication provider that is not LocalAuthentication.
 */
class SessionOnlyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isApiKeyRequest = $request->getHeaderLine('x-api-key') !== '';

        if (!$isApiKeyRequest) {
            try {
                $isApiKeyRequest = !AuthenticationManager::getAuthenticationProvider() instanceof LocalAuthentication;
            } catch (\Throwable $exception) {
                $isApiKeyRequest = true;
            }
        }

        if ($isApiKeyRequest) {
            LoggerUtils::getAuthLogger()->warning('Rejected API-key request for a session-only route', [
                'path'   => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ]);

            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => gettext('This action requires an interactive session.'),
                'code'  => 403,
            ]));

            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
