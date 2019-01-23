<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

class AuthMiddleware {

    private $user;
    private $apiKey;

    public function __invoke( Request $request, Response $response, callable $next )
    {
        if (!$this->isPath( $request, "public")) {
            $this->apiKey = $request->getHeader("x-api-key");
            if (!empty($this->apiKey)) {
                $user = UserQuery::create()->findOneByApiKey($this->apiKey);
                if (!empty($user)) {
                    LoggerUtils::getAppLogger()->debug($user->getName() . " : " . gettext("logged via API Key."));
                    $this->user = $user;
                } else {
                    LoggerUtils::getAppLogger()->warn(gettext("logged via InValid API Key."));
                    session_destroy();
                }
            }
            if (empty($this->user)) {
                $this->user = $_SESSION['user'];
            } else {
                $_SESSION['user'] = $this->user;
                $_SESSION['tLastOperation'] = time();
            }

            if (!$this->isUserSessionValid($request)) {
                return $response->withStatus(401, gettext('No logged in user'));
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
          if(!$this->isPath( $request, "background"))
          {
            //Only update tLastOperation if the request was an actual user request.
            //Background requests should not update tLastOperation
            $_SESSION['tLastOperation'] = time();
          }
        }
      }
      return true;
    }

    private function isPath(Request $request, $pathPart) {
        $pathAry = explode("/", $request->getUri()->getPath());
        if (!empty($pathAry) && $pathAry[0] === $pathPart) {
            return true;
        }
        return false;
    }

}
