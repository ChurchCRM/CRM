<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Portal\Auth\LoginAsPerson;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * The Login as Person start route does not exist while `bAllowLoginAsPerson` is off
 * (member sign-in design §6.5). Stop is never behind it, so nobody is left inside
 * a member's portal when the setting is turned off.
 */
final class LoginAsPersonEnabledMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!LoginAsPerson::isEnabled()) {
            throw new HttpNotFoundException($request);
        }

        return $handler->handle($request);
    }
}
