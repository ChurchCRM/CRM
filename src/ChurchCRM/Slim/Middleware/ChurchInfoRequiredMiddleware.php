<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that enforces church name configuration on first run.
 *
 * When sChurchName is empty (fresh install), browser requests from admin
 * users are redirected to the church-info setup page. API routes and the
 * church-info page itself are exempt from the redirect. Non-admin users
 * pass through since they cannot complete the setup.
 */
class ChurchInfoRequiredMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    /**
     * Admin path for the church-info setup page.
     * Used both for the exempt-path check and the redirect target.
     */
    private const CHURCH_INFO_PATH = '/admin/system/church-info';

    /**
     * Admin paths (relative to root) exempt from the church-info redirect.
     * Both GET and POST to church-info must be reachable so the user can
     * load and submit the form without being redirected in a loop.
     *
     * @var string[]
     */
    private const EXEMPT_PATHS = [
        self::CHURCH_INFO_PATH,
        '/v2/user/current/changepassword',
        '/v2/user/current/manage2fa',
        '/v2/user/current/enroll2fa',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only enforce for browser (HTML) requests — API calls must pass through.
        if (!$this->isBrowserRequest($request)) {
            return $handler->handle($request);
        }

        // If church name is already configured, nothing to do.
        if (!empty(SystemConfig::getValue('sChurchName'))) {
            return $handler->handle($request);
        }

        // Only a signed-in admin can complete the church-info setup. A provider
        // left by a timed-out or half-finished sign-in still names its user, and
        // sending it to church-info from the /admin sign-in page would loop.
        if (!AuthenticationManager::validateUserSessionIsActive(false)) {
            return $handler->handle($request);
        }

        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            return $handler->handle($request);
        }

        $path     = $request->getUri()->getPath();
        $rootPath = SystemURLs::getRootPath();

        // Allow exempt pages through so the administrator can complete the setup
        // without being redirected in a loop. Pre-compute full paths to avoid
        // repeated string concatenations inside the loop.
        foreach (self::EXEMPT_PATHS as $exemptPath) {
            if (str_starts_with($path, $rootPath . $exemptPath)) {
                return $handler->handle($request);
            }
        }

        return $this->redirectToChurchInfo();
    }

    private function redirectToChurchInfo(): ResponseInterface
    {
        $response    = new Response();
        $redirectUrl = SystemURLs::getRootPath() . self::CHURCH_INFO_PATH;

        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }
}
