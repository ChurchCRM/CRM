<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseAuthRoleMiddleware implements MiddlewareInterface
{
    protected User $user;

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $this->user = AuthenticationManager::getCurrentUser();
        } catch (\Throwable $ex) {
            $response = new Response();
            return $response->withStatus(401, gettext('No logged in user'));
        }

        if (!$this->hasRole()) {
            $response = new Response();
            return $response->withStatus(403, $this->noRoleMessage());
        }

        return $handler->handle($request);
    }

    abstract protected function hasRole();

    abstract protected function noRoleMessage();
}
