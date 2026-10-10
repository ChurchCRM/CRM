<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\ImpersonationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Answers 404 for the Login as User routes while `bAllowLoginAsUser` is off (#9843).
 *
 * A masquerade that is already running can still be exited after the setting is
 * turned off, so nobody is stranded inside another user's account.
 */
class LoginAsUserEnabledMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!ImpersonationService::isEnabled() && !ImpersonationService::isActive()) {
            throw new HttpNotFoundException($request);
        }

        return $handler->handle($request);
    }
}
