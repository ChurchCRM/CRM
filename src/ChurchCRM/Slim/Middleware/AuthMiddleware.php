<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!$this->isPath($request, "public")) {
            $apiKey = $request->getHeader("x-api-key");
            if (!empty($apiKey)) {
                $authenticationResult = AuthenticationManager::authenticate(new APITokenAuthenticationRequest($apiKey[0]));
                if (! $authenticationResult->isAuthenticated) {
                    AuthenticationManager::endSession(true);
                    return $response->withStatus(401, gettext('No logged in user'));
                }
            } else if (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, "background"))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // User with an active browser session is still authenticated.
                // don't really need to do anything here...
            } else {
                return $response->withStatus(401, gettext('No logged in user'));
            }

            return $next($request, $response)->withHeader("CRM_USER_ID", AuthenticationManager::getCurrentUser()->getId());
        }
        return $next($request, $response);
    }

    private function isPath(Request $request, $pathPart)
    {
        $pathAry = explode("/", $request->getUri()->getPath());
        if (!empty($pathAry) && $pathAry[0] === $pathPart) {
            return true;
        }
        return false;
    }
}
