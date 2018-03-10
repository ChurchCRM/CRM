<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;

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

            return $next( $request, $response )->withHeader( "CRM_USER_ID", $this->user->getId());
        }
        return $next( $request, $response );
    }

    private function isUserSessionValid(Request $request) {
      if (empty($this->user)) {
        return false;
      }
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
           return false;
        } else {
          if(!$this->isBackgroundRequest( $request->getUri()->getPath()))
          {
            //Only update tLastOperation if the request was an actual user request.
            //Background requests should not update tLastOperation
            $_SESSION['tLastOperation'] = time();
          }
        }
      }
      return true;
    }

    private function isPublic($path) {
        $pathAry = explode("/", $path);
        if (!empty($path) && $pathAry[0] === "public") {
            return true;
        }
        return false;
    }

    private function isBackgroundRequest($path) {
      $pathAry = explode("/", $path);
      if (!empty($path) && ($pathAry[0] === "run" || $pathAry[0] === "dashboard")) {
          return true;
      }
      return false;
    }
}
