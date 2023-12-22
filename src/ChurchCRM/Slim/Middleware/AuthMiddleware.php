<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (!str_contains($request->getUri(), 'api/public')) {
            $apiKey = $request->getHeader('x-api-key');
            if (!empty($apiKey)) {
                $authenticationResult = AuthenticationManager::authenticate(new APITokenAuthenticationRequest($apiKey[0]));
                if (!$authenticationResult->isAuthenticated) {
                    AuthenticationManager::endSession(true);
                    $response = new Response();
                    return $response->withStatus(401, gettext('No logged in user'));
                }
            } elseif (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, 'background'))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // User with an active browser session is still authenticated.
                // don't really need to do anything here...
            } else {
                $response = new Response();
                return $response->withStatus(401, gettext('No logged in user'));
            }
        }

        return $handler->handle($request);
    }

    private function isPath(Request $request, string $pathPart): bool
    {
        $pathAry = explode('/', $request->getUri()->getPath());
        if (!empty($pathAry) && $pathAry[0] === $pathPart) {
            return true;
        }

        return false;
    }
}
