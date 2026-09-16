<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
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
        if (!$this->isPublicPath($request)) {
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

                // Confine EditSelf-only users to the self-service flow — they have no
                // module permissions and no business on the internal API surface.
                // Zero-permission users are NOT blocked: they retain read-only access
                // to people/family records (read-default policy, #9003). Writes are
                // denied by the per-route role middleware.
                //
                // isLimitedAccessAllowedPath() is consulted here as well as in the
                // session branch below: the Volunteer v2 member surface must be reachable
                // by API key too, or cy.makePrivateEditSelfAPICall() always returns 403
                // and the self-service specs cannot be written at all (#9706, design §4.7).
                $apiUser = AuthenticationManager::getCurrentUser();
                if ($apiUser->isEditSelfExclusive() && !$this->isLimitedAccessAllowedPath($request)) {
                    $response = new Response();
                    $response->getBody()->write(json_encode(['error' => 'Account has limited permissions. Contact an administrator.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, 'background'))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // Confine EditSelf-only users to the Member Portal, the whole of
                // their self-service surface (design P10, #9863).
                // BUT allow them through if they need to change their password — blocking
                // the change-password page locks new users out permanently. See #8680.
                // Zero-permission users are NOT redirected: they retain read-only access
                // to people/family records (read-default policy, #9003). Writes are denied
                // by the per-route role middleware and the per-page permission guards.
                $sessionUser = AuthenticationManager::getCurrentUser();
                if ($sessionUser->isEditSelfExclusive() && !$this->isLimitedAccessAllowedPath($request)) {
                    if ($this->isBrowserRequest($request)) {
                        $rootPath = SystemURLs::getRootPath();
                        return (new Response())->withStatus(302)->withHeader('Location', $rootPath . '/portal/');
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
     * Paths that carry no authentication at all.
     *
     *  - /api/public/…     — the public API surface
     *  - /portal/theme/…   — Member Portal theme assets (#9863). Include/ is
     *    deny-all at the web-server level, so a theme's stylesheet, script,
     *    images and fonts are streamed by the application instead. The route
     *    serves nothing but allow-listed static files, and staying public is
     *    what keeps a cached portal page from breaking on a logged-out asset
     *    request (design §2.2 step 6).
     *
     * Both are built with the install's root path, so a subdirectory install
     * ('/crm/api/public') matches exactly as a root install does.
     */
    private function isPublicPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        foreach (['/api/public', '/portal/theme'] as $publicPrefix) {
            $publicPath = $rootPath . $publicPrefix;
            if ($path === $publicPath || str_starts_with($path, $publicPath . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the current request targets a path an EditSelf-only session
     * is allowed to reach. Everything else is redirected to the Member Portal.
     *
     * Allowed paths:
     *  - /portal and /portal/…          — the Member Portal itself (#9863)
     *  - /api/portal/…                  — the portal's own API surface
     *  - /user/current/changepassword   — forced password change on first login
     *  - /user/current/manage2fa        — forced 2FA enrollment when bRequire2FA is on
     *  - /user/current/enroll2fa        — backward-compat alias for manage2fa
     *  - /user/impersonate/exit         — the way out of an admin masquerade (#9843);
     *    without it the exit request itself would be redirected away
     *  - /api/volunteer/me/…, /volunteer/my-schedule, /volunteer/opportunities — the
     *    Volunteer v2 member surface (#9706, design §4.7), only while the rollout
     *    state includes V2; every route behind them derives the acting person from
     *    the session and accepts no personId. These move into /portal with MP6 (#9867).
     *
     * Without the auth-flow exemptions, limited-permission users get stuck in a
     * redirect loop because AuthMiddleware blocks the page the auth system is
     * sending them to. See #8680.
     */
    private function isLimitedAccessAllowedPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        foreach (['/portal', '/api/portal'] as $allowedPrefix) {
            $allowedPath = $rootPath . $allowedPrefix;
            if ($path === $allowedPath || str_starts_with($path, $allowedPath . '/')) {
                return true;
            }
        }

        if (
            str_contains($path, '/user/current/changepassword')
            || str_contains($path, '/user/current/manage2fa')
            || str_contains($path, '/user/current/enroll2fa')
            || str_contains($path, '/user/impersonate/exit')
        ) {
            return true;
        }

        if (!User::isVolunteerV2Enabled()) {
            return false;
        }

        return str_contains($path, '/api/volunteer/me/')
            || str_contains($path, '/volunteer/my-schedule')
            || str_contains($path, '/volunteer/opportunities');
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
