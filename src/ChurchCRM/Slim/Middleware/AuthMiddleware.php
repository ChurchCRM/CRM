<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
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
                $logger = LoggerUtils::getAppLogger();
                $logger->debug('API key authentication attempt', [
                    'path' => $request->getUri()->getPath(),
                    'has_key' => !empty($apiKey[0])
                ]);
                $authenticationResult = AuthenticationManager::authenticate(new APITokenAuthenticationRequest($apiKey[0]));
                if (!$authenticationResult->isAuthenticated) {
                    AuthenticationManager::endSession(true);
                    $logger->warning('Invalid API key authentication attempt', [
                        'path' => $request->getUri()->getPath(),
                        'method' => $request->getMethod()
                    ]);
                    $response = new Response();
                    $errorBody = json_encode(['error' => gettext('Invalid API key'), 'code' => 401]);
                    $response->getBody()->write($errorBody);
                    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
                }
                $logger->debug('API key authentication successful', [
                    'path' => $request->getUri()->getPath()
                ]);
            } elseif (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, 'background'))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // User with an active browser session is still authenticated.
                // don't really need to do anything here...
            } else {
                $logger = LoggerUtils::getAppLogger();
                $logger->warning('No authenticated user or session', [
                    'path' => $request->getUri()->getPath(),
                    'method' => $request->getMethod()
                ]);

                // Check if this is a browser request - redirect to login instead of JSON error
                if ($this->isBrowserRequest($request)) {
                    return $this->redirectToLogin();
                }

                $response = new Response();
                $errorBody = json_encode(['error' => gettext('No logged in user'), 'code' => 401]);
                $response->getBody()->write($errorBody);
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
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

    /**
     * Check if request is from a browser (expects HTML) vs API client (expects JSON)
     */
    private function isBrowserRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // API routes should always return JSON
        if (str_contains($path, '/api/')) {
            return false;
        }

        // Check Accept header - browsers typically send text/html
        $acceptHeader = $request->getHeaderLine('Accept');
        if (!empty($acceptHeader)) {
            // If client explicitly wants JSON, it's an API request
            if (str_contains($acceptHeader, 'application/json') && !str_contains($acceptHeader, 'text/html')) {
                return false;
            }
            // If client accepts HTML, treat as browser
            if (str_contains($acceptHeader, 'text/html')) {
                return true;
            }
        }

        // Check X-Requested-With header (AJAX requests)
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        // Default to browser for non-API routes
        return true;
    }

    /**
     * Redirect to the login page
     */
    private function redirectToLogin(): ResponseInterface
    {
        $response = new Response();
        $redirectUrl = SystemURLs::getRootPath() . '/session/begin';

        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }
}
