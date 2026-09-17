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
                // module permissions and no business on the internal API surface,
                // apart from the self-service auth-flow paths (password change,
                // 2FA enrollment) listed in self::AUTH_FLOW_EXEMPT_PATHS — and the Member Portal API (#9863).
                // Zero-permission users are NOT blocked: they retain read-only access
                // to people/family records (read-default policy, #9003). Writes are
                // denied by the per-route role middleware.
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
                // BUT allow them through for the self-service auth-flow paths: blocking
                // the change-password page locks new users out permanently (#8680), and
                // blocking the 2FA APIs makes enrollment impossible (#9886).
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
     * Paths (relative to the install root) that must stay reachable for a user
     * who has no module permissions, over and above the Member Portal itself.
     * Without these exemptions, limited-permission users get stuck in a
     * redirect loop (pages) or a 403 (XHRs) because AuthMiddleware blocks the
     * very flow the auth system is sending them to. See #8680 and #9886.
     *
     * Every entry is self-service: each handler acts on the requesting user's
     * own account only, via AuthenticationManager::getCurrentUser(). Keep the
     * list exact — never exempt a whole route group.
     *
     * Pages:
     *  - /v2/user/current/changepassword — forced password change on first login
     *  - /v2/user/current/manage2fa      — forced 2FA enrollment when bRequire2FA is on
     *  - /v2/user/current/enroll2fa      — backward-compat alias for manage2fa
     *
     * APIs called by the manage2fa page bundle (webpack/two-factor-enrollment.js):
     *  - /api/user/current/2fa-status
     *  - /api/user/current/get2faqrcode
     *  - /api/user/current/refresh2fasecret
     *  - /api/user/current/refresh2farecoverycodes
     *  - /api/user/current/remove2fasecret
     *  - /api/user/current/test2FAEnrollmentCode
     */
    private const AUTH_FLOW_EXEMPT_PATHS = [
        '/v2/user/current/changepassword',
        '/v2/user/current/manage2fa',
        '/v2/user/current/enroll2fa',
        '/api/user/current/2fa-status',
        '/api/user/current/get2faqrcode',
        '/api/user/current/refresh2fasecret',
        '/api/user/current/refresh2farecoverycodes',
        '/api/user/current/remove2fasecret',
        '/api/user/current/test2FAEnrollmentCode',
    ];

    /**
     * Check whether the current request targets a path an EditSelf-only user
     * is allowed to reach. Everything else is redirected to the Member Portal
     * (browser) or refused with 403 (API).
     *
     * Allowed:
     *  - /portal and /portal/…  — the Member Portal itself (#9863)
     *  - /api/portal/…          — the portal's own API surface
     *  - the self-service auth-flow paths in self::AUTH_FLOW_EXEMPT_PATHS
     *
     * The two prefixes match whole path segments; the exempt paths are compared
     * whole against SystemURLs::getRootPath() . $exemptPath, so a subdirectory
     * installation (/crm/v2/user/current/manage2fa) still matches, while a path
     * that merely contains or ends with an exempt path — /evil/api/user/current/
     * 2fa-status — does not, so the gate cannot be widened by a future
     * catch-all route.
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

        foreach (self::AUTH_FLOW_EXEMPT_PATHS as $exemptPath) {
            if ($path === $rootPath . $exemptPath) {
                return true;
            }
        }

        return false;
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
