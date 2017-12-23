<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\User;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthAdminMiddleware {

    public function __invoke( Request $request, Response $response, callable $next )
    {
        /**
         * @var User $user
         */
        $user = $_SESSION['user'];
        if (empty($user) || !$user->isAdmin()) {
            return $response->withStatus( 401, gettext('User must be an Admin'));
        }
        return $next( $request, $response );
    }
}
