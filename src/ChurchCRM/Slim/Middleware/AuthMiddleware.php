<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\SystemService;

class AuthMiddleware {

    private $user;

    public function __invoke( Request $request, Response $response, callable $next )
    {
        if (!$this->isPublic( $request->getUri()->getPath())) {
            $this->user = $_SESSION['user'];
            if (empty($this->user)) {
                return $response->withHeader("uri", $uri)->withStatus( 401)->withJson( ["message" => gettext('No logged in user')]);
            }
            return $next( $request, $response )->withHeader( "CRM_USER_ID", $this->user->getId())->withHeader("uri", $uri);
        }  
        return $next( $request, $response );
    }
    
    private function isPublic($path) {
        $pathAry = explode("/", $path);
        if (!empty($path) && $pathAry[0] === "public") {
            return true;
        }
        return false;
    }
}
