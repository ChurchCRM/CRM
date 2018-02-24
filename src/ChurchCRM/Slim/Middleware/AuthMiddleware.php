<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\SystemService;

class AuthMiddleware {

    private $user;
    private $apiKey;

    public function __invoke( Request $request, Response $response, callable $next )
    {
        if (!$this->isPublic( $request->getUri()->getPath())) {
            $this->apiKey = $request->getHeader("x-api-key");
            if (!empty($this->apiKey)) {
                $this->user = UserQuery::create()->findOneByApiKey($this->apiKey);

            }
            if (empty($this->user)) {
                $this->user = $_SESSION['user'];
            } else {
                $_SESSION['user'] = $this->user;
            }

            if (empty($this->user)) {
                return $response->withStatus(401, gettext('No logged in user'));
            }


            return $next( $request, $response )->withHeader( "CRM_USER_ID", $this->user->getId());
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
