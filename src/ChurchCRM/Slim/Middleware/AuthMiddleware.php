<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!str_starts_with($request->getUri()->getPath(), '/api/public')) {
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

    private function isPath(ServerRequestInterface $request, string $pathPart): bool
    {
        $pathAry = explode('/', $request->getUri()->getPath());
        if ($pathAry[0] === $pathPart) {
            return true;
        }

        return false;
    }
}
