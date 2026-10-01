<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Refuses a request when the session is already an admin masquerade (#9843).
 *
 * Nesting masquerades would make the auth log ambiguous about who did what, so
 * a second start is answered with 409 Conflict. This must run *before* the
 * admin role gate: while masquerading the session normally belongs to a
 * non-admin, and a bare 403 would hide the real reason the request was refused.
 */
class NoActiveMasqueradeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ImpersonationService::isActive()) {
            LoggerUtils::getAuthLogger()->warning('Rejected a nested masquerade attempt', [
                'path'   => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ]);

            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => gettext('A masquerade is already in progress. Exit it before starting another.'),
                'code'  => 409,
            ]));

            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
