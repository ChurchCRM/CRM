<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Construct the full public API path including any subdirectory installation
        // Examples: '/api/public' (root install), '/crm/api/public' (subdirectory install)
        $publicApiPath = SystemURLs::getRootPath() . '/api/public';
        
        if (!str_starts_with($request->getUri()->getPath(), $publicApiPath)) {
            $apiKey = $request->getHeader('x-api-key');
            if (!empty($apiKey)) {
                $logger = LoggerUtils::getAppLogger();
                $logger->debug('API key authentication attempt', [
                    'path' => $request->getUri()->getPath(),
                    'has_key' => !empty($apiKey[0])
                ]);
                $authenticationResult = AuthenticationManager::authenticate(new APITokenAuthenticationRequest($apiKey[0]));
                if (!$authenticationResult->isAuthenticated) {
                    try {
                        AuthenticationManager::endSession(true);
                    } catch (\Exception $e) {
                        $logger->debug('Error ending session during failed API auth', ['exception' => $e]);
                    }
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

                // Block users with no admin permissions from API access (GHSA-5w59-32c8-933v)
                $apiUser = AuthenticationManager::getCurrentUser();
                if ($apiUser->hasNoAdminPermissions()) {
                    $response = new Response();
                    $response->getBody()->write(json_encode(['error' => 'Account has limited permissions. Contact an administrator.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, 'background'))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // Block users with no admin permissions from MVC/API access (GHSA-5w59-32c8-933v)
                // BUT allow them through if they need to change their password — blocking
                // the change-password page locks new users out permanently. See #8680.
                $sessionUser = AuthenticationManager::getCurrentUser();
                if ($sessionUser->hasNoAdminPermissions() && !$this->isPasswordChangePath($request)) {
                    if ($this->isBrowserRequest($request)) {
                        $rootPath = SystemURLs::getRootPath();
                        return (new Response())->withStatus(302)->withHeader('Location', $rootPath . '/external/limited-access');
                    }
                    // API request — return 403
                    $response = new Response();
                    $response->getBody()->write(json_encode(['error' => 'Account has limited permissions. Contact an administrator.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }

                // User with an active browser session is still authenticated.
                // For browser requests (non-background), enforce any required redirect steps (e.g. forced password change).
                // Use a PSR-15 response redirect rather than calling ensureAuthentication() which exits via header().
                if ($this->isBrowserRequest($request) && !$this->isPath($request, 'background')) {
                    $result = AuthenticationManager::getAuthenticationProvider()->validateUserSessionIsActive(true);
                    if ($result->nextStepURL !== null) {
                        return (new Response())->withStatus(302)->withHeader('Location', $result->nextStepURL);
                    }
                }
            } else {
                $logger = LoggerUtils::getAppLogger();
                $logger->warning('No authenticated user or session', [
                    'path' => $request->getUri()->getPath(),
                    'method' => $request->getMethod()
                ]);

                // Check if this is a browser request - redirect to login instead of JSON error
                if ($this->isBrowserRequest($request)) {
                    return $this->redirectToLogin($request);
                }

                $response = new Response();
                $errorBody = json_encode(['error' => gettext('No logged in user'), 'code' => 401]);
                $response->getBody()->write($errorBody);
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }

    /**
     * Check whether the current request targets the forced-password-change page.
     * Users who must change their password on first login should always be able
     * to reach this page, even if they have no other admin permissions. See #8680.
     */
    private function isPasswordChangePath(ServerRequestInterface $request): bool
    {
        return str_contains($request->getUri()->getPath(), '/user/current/changepassword');
    }

    private function isPath(ServerRequestInterface $request, string $pathPart): bool
    {
        // explode produces an empty string at index 0 for paths starting with '/',
        // so use in_array to check if the segment exists anywhere in the path
        $pathAry = explode('/', $request->getUri()->getPath());
        return in_array($pathPart, $pathAry, true);
    }

    /**
     * Redirect to the login page, storing the originally requested path in the session
     * so the user can be returned there after successful login.
     * The return path is stored server-side (session) to prevent open-redirect attacks
     * via a crafted query parameter.
     */
    private function redirectToLogin(ServerRequestInterface $request): ResponseInterface
    {
        // Capture the originally requested path (with query string) for post-login redirect.
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        $fullPath = $query !== '' ? $path . '?' . $query : $path;

        // Validate the path (empty string fallback means "don't store" on failure).
        // RedirectUtils::stripAndValidatePath() strips the root path and validates for safety.
        $safePath = RedirectUtils::stripAndValidatePath($fullPath);
        if ($safePath !== '') {
            $_SESSION['location'] = $safePath;
        }

        $response = new Response();
        $redirectUrl = SystemURLs::getRootPath() . '/session/begin';

        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }

}
