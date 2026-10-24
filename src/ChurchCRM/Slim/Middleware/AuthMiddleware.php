<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\Authentication\StaffSignInPage;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\LoginAsUser;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\Middleware\VolunteerPortalMemberMiddleware;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
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
        // An API-key provider must never outlive its own request, even under a
        // long-running PHP worker that reuses the process.
        AuthenticationManager::clearRequestProvider();

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
                    $errorBody = json_encode(SlimUtils::buildErrorPayload(gettext('Invalid API key'), 401));
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
                //
                // isLimitedAccessAllowedPath() is consulted here as well as in the
                // session branch below: the Volunteer v2 member surface must be reachable
                // by API key too, or cy.makePrivateEditSelfAPICall() always returns 403
                // and the self-service specs cannot be written at all (#9706, design §4.7).
                $apiUser = AuthenticationManager::getCurrentUser();
                if ($apiUser->isEditSelfExclusive() && !$this->isLimitedAccessAllowedPath($request)) {
                    $response = new Response();
                    $response->getBody()->write(json_encode(SlimUtils::buildErrorPayload(gettext('Account has limited permissions. Contact an administrator.'), 403)));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif (LoginAsUser::isActive()) {
                // Login as User (member sign-in design §6.5): the administrator's staff
                // session is set aside, and every staff route stays closed until Stop.
                if (!$this->isPortalPath($request) && !$this->isPortalVolunteerApiPath($request)) {
                    return $this->refuseDuringLoginAsUser($request);
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
                    $response->getBody()->write(json_encode(SlimUtils::buildErrorPayload(gettext('Account has limited permissions. Contact an administrator.'), 403)));
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
            } elseif (($this->isPortalPath($request) || $this->isPortalVolunteerApiPath($request)) && PortalSessionStore::has()) {
                // A portal session with no staff user (member sign-in design §3).
                // PortalSessionMiddleware validates it on /portal and /api/portal, and
                // VolunteerPortalMemberMiddleware on the volunteer API the portal pages
                // call; every other route refuses it below.
            } elseif ($this->isPortalPath($request) && RememberedDeviceCookie::isPresent($request)) {
                // A remembered device (§6.2): PortalSessionMiddleware starts its
                // session, or sends the visitor to /portal/login.
            } elseif (StaffSignInPage::isRequestFor($request)) {
                // The /admin root is the staff sign-in page (member sign-in design §7):
                // signed out, or a member session, it shows the sign-in form.
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
                $errorBody = json_encode(SlimUtils::buildErrorPayload(gettext('No logged in user'), 401));
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
     *  - /portal/login/…   — member sign-in by email (member sign-in design
     *    §6.1), which a signed-out visitor must reach.
     *  - /portal/email/confirm/… — the page an email-change link opens (member
     *    sign-in design §9); the token in the link is what identifies the request.
     *
     * Each is built with the install's root path, so a subdirectory install
     * ('/crm/api/public') matches exactly as a root install does.
     */
    private function isPublicPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        foreach (['/api/public', '/portal/theme', '/portal/login', '/portal/email/confirm'] as $publicPrefix) {
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
     * list exact — never exempt a whole route group. The Member Portal itself,
     * and the Volunteer v2 member and team-leader APIs, are prefix rules in
     * isLimitedAccessAllowedPath(), not entries here.
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
     *  - /api/ministries/me/…    — the Volunteer v2 member API (#9706,
     *    volunteer design §4.7), only while the rollout state includes V2. Every
     *    route behind it derives the acting person from the session and accepts
     *    no personId.
     *
     * The Volunteer v2 member pages are portal pages (MP6, #9867), covered by the
     * `/portal` prefix above; their bundles call `/api/ministries/me/`.
     *
     * **The team-leader exemption (#9868).** A self-service login that holds a
     * volunteer `team` scope may reach the whole of `/api/ministries/`, not just
     * `/me/`. That is the Member Portal's revision of D14 (P17, #9867) made
     * reachable: My Teams (MP7) is a portal page, and everything on it — the
     * qualification grid, the schedules, the occurrences, the assignments — is
     * served by the coordinator half of that API. It widens the API surface and
     * nothing else: `/ministries` (the admin MVC area) is still not on this list,
     * so such a login is still bounced to `/portal/` if it asks for one of those
     * pages. What the API then LETS them do is decided exactly where it was
     * before — `VolunteerTeam/Position/Schedule/Occurrence/AssignmentMiddleware`
     * refuse every record outside the teams they lead, and the ministry-level
     * routes refuse them outright (volunteer design §4.4, §4.5).
     *
     * Every prefix — `/portal`, `/api/portal`, `/api/ministries/me/`,
     * `/api/ministries/` — is anchored at SystemURLs::getRootPath() and matches
     * whole path segments, and the exempt paths are compared whole against
     * SystemURLs::getRootPath() . $exemptPath, so a subdirectory installation
     * (/crm/v2/user/current/manage2fa) still matches, while a path that merely
     * contains or ends with an allowed path — /evil/api/user/current/2fa-status,
     * /api/person/1/api/ministries/me/permissions — does not, so the gate cannot
     * be widened by a future catch-all route.
     */
    private function isLimitedAccessAllowedPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        if ($this->isPortalPath($request)) {
            return true;
        }

        foreach (self::AUTH_FLOW_EXEMPT_PATHS as $exemptPath) {
            if ($path === $rootPath . $exemptPath) {
                return true;
            }
        }

        if (!User::isVolunteerV2Enabled()) {
            return false;
        }

        $ministriesApiPath = $rootPath . '/api/ministries/';
        if (!str_starts_with($path, $ministriesApiPath)) {
            return false;
        }

        if (str_starts_with($path, $ministriesApiPath . 'me/')) {
            return true;
        }

        // The rest of the volunteer API, for somebody with team authority only
        // (#9868; member sign-in design §8.4).
        $user = AuthenticationManager::getCurrentUser();

        return $user instanceof User && (new VolunteerAuthorizationService())->hasTeamAuthority($user);
    }

    /** `/api/ministries/…` while Volunteer v2 is on: the volunteer API the portal pages call. */
    private function isPortalVolunteerApiPath(ServerRequestInterface $request): bool
    {
        return User::isVolunteerV2Enabled() && VolunteerPortalMemberMiddleware::isVolunteerApiPath($request);
    }

    /** `/portal` and `/api/portal`, anchored at the root path and matched by whole segments. */
    private function isPortalPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        foreach (['/portal', '/api/portal'] as $portalPrefix) {
            $portalPath = $rootPath . $portalPrefix;
            if ($path === $portalPath || str_starts_with($path, $portalPath . '/')) {
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

    private function refuseDuringLoginAsUser(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->isBrowserRequest($request)) {
            PortalExtension::addFlash('warning', LoginAsUser::staffPagesClosedMessage());

            return (new Response())->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/portal/');
        }

        return SlimUtils::renderErrorJSON(new Response(), LoginAsUser::staffPagesClosedMessage(), [], 403);
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

        $redirectUrl = $this->isPortalPath($request)
            ? AuthenticationManager::getPortalLoginURL()
            : AuthenticationManager::getStaffSignInURL();

        return (new Response())->withStatus(302)->withHeader('Location', $redirectUrl);
    }

}
