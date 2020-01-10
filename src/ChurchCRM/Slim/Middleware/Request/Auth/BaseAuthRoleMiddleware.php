<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\User;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class BaseAuthRoleMiddleware
{

    /**
     * @var  User $user
     */
    protected $user;

    public function __invoke(Request $request, Response $response, callable $next)
    {
        $this->user = AuthenticationManager::GetCurrentUser();
        if (empty($this->user)) {
            return $response->withStatus(401, gettext('No logged in user'));
        }

        if (!$this->hasRole()) {
            return $response->withStatus(403, $this->noRoleMessage());
        }
        return $next($request, $response);
    }

    abstract function hasRole();

    abstract function noRoleMessage();
}
