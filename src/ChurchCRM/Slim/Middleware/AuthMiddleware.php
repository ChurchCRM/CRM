<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\SystemService;

class AuthMiddleware {

    public function __invoke( Request $request, Response $response, callable $next )
    {
        $user = $_SESSION['user'];
        if (empty($user)) {
            return $response->withStatus( 401)->withJson( ["message" => gettext('No logged in user')]);
        }
        return $next( $request, $response )->withHeader( "CRM_USER_ID", $user->getId());
    }
}
