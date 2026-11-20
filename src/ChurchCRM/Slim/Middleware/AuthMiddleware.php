<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\LoginAsPerson;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\Middleware\VolunteerPortalMemberMiddleware;
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

            } elseif (LoginAsPerson::isActive()) {
                // Login as Person (member sign-in design §6.5): the administrator's staff
                // session is set aside, and every staff route stays closed until Stop.
                if (!$this->isPortalPath($request) && !$this->isPortalVolunteerApiPath($request)) {
                    return $this->refuseDuringLoginAsPerson($request);
                }
            } elseif (AuthenticationManager::validateUserSessionIsActive(!$this->isPath($request, 'background'))) {
                // validate the user session; however, do not update tLastOperation if the requested path is "/background"
                // since /background operations do not connotate user activity.

                // User with an active browser session is still authenticated.
                // For browser requests (non-background), enforce any required redirect steps (e.g. forced password change).
                // Use a PSR-15 response redirect rather than calling ensureAuthentication() which exits via header().
                if ($this->isBrowserRequest($request) && !$this->isPath($request, 'background')) {
                    $result = AuthenticationManager::getAuthenticationProvider()->validateUserSessionIsActive(true);
                    if ($result->nextStepURL !== null) {
                        return (new Response())->withStatus(302)->withHeader('Location', $result->nextStepURL);
                    }
                } elseif (!$this->isPath($request, 'background') && !$this->isOwnAccountApiPath($request)) {
                    // A session still authenticated with a next step is one whose 2FA enrollment
                    // is overdue; its API calls stop here, except the ones enrollment itself uses.
                    $result = AuthenticationManager::getAuthenticationProvider()->validateUserSessionIsActive(false);
                    if ($result->nextStepURL !== null) {
                        return SlimUtils::renderErrorJSON(new Response(), gettext('Set up two-factor authentication to continue.'), [], 403);
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
     *  - /portal/register  — optional family registration form, controlled by
     *    the public registration setting.
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

        foreach (['/api/public', '/portal/theme', '/portal/login', '/portal/register', '/portal/email/confirm'] as $publicPrefix) {
            $publicPath = $rootPath . $publicPrefix;
            if ($path === $publicPath || str_starts_with($path, $publicPath . '/')) {
                return true;
            }
        }

        return false;
    }

    /** `/api/ministries/…`: the volunteer API the portal pages call. */
    private function isPortalVolunteerApiPath(ServerRequestInterface $request): bool
    {
        return VolunteerPortalMemberMiddleware::isVolunteerApiPath($request);
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

    /** `/api/user/current/…`: the signed-in user's own account calls, which 2FA enrollment uses. */
    private function isOwnAccountApiPath(ServerRequestInterface $request): bool
    {
        return str_starts_with($request->getUri()->getPath(), SystemURLs::getRootPath() . '/api/user/current/');
    }

    private function isPath(ServerRequestInterface $request, string $pathPart): bool
    {
        // explode produces an empty string at index 0 for paths starting with '/',
        // so use in_array to check if the segment exists anywhere in the path
        $pathAry = explode('/', $request->getUri()->getPath());
        return in_array($pathPart, $pathAry, true);
    }

    private function refuseDuringLoginAsPerson(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->isBrowserRequest($request)) {
            PortalExtension::addFlash('warning', LoginAsPerson::staffPagesClosedMessage());
            $response = (new Response())->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/portal/');
        } else {
            $response = SlimUtils::renderErrorJSON(new Response(), LoginAsPerson::staffPagesClosedMessage(), [], 403);
        }

        $historyId = LoginAsPerson::historyId();
        if ($historyId !== null && LoginAsPerson::isWriteMethod($request->getMethod())) {
            LoginAsPerson::recordAction($historyId, $request->getMethod(), $request->getUri()->getPath(), $response->getStatusCode());
        }

        return $response;
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
