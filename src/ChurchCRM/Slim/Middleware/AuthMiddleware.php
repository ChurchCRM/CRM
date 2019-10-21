<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Authentication\AuthenticationManager;

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
            // validate the user session; however, do not update tLastOperation if the requested path is "/background"
            // since /background operations do not connotate user activity.
            else if (AuthenticationManager::ValidateUserSessionIsActive(!$this->isPath( $request, "background"))) {
                $this->user = AuthenticationManager::GetCurrentUser();
            }
            else {
                return $response->withStatus(401, gettext('No logged in user'));
            }
            return $next( $request, $response )->withHeader( "CRM_USER_ID", $this->user->getId());
        }
        return $next( $request, $response );
    }

    private function isPath(Request $request, $pathPart) {
        $pathAry = explode("/", $request->getUri()->getPath());
        if (!empty($pathAry) && $pathAry[0] === $pathPart) {
            return true;
        }
        return false;
    }

}
