<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

abstract class BaseAuthRoleMiddleware
{
    /**
     * @var User
     */
    protected $user;

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        $this->user = AuthenticationManager::getCurrentUser();
        if (empty($this->user)) {
            $response = $handler->handle($request);

            return $response->withStatus(401, gettext('No logged in user'));
        }

        if (!$this->hasRole()) {
            return $response->withStatus(403, $this->noRoleMessage());
        }

        return $handler->handle($request);
    }

    abstract protected function hasRole();

    abstract protected function noRoleMessage();
}
