<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Keeps an administrator on the Setup Dashboard until the church has a name.
 *
 * While sChurchName is empty (fresh install), browser requests from admin
 * users go to the Setup Dashboard. Its three paths stay reachable: demo data,
 * church information and restoring a backup. API routes pass through.
 * Non-admin users pass through since they cannot complete the setup.
 */
class ChurchInfoRequiredMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    private const SETUP_PATH = '/admin/setup';

    /**
     * Admin paths (relative to root) exempt from the church-info redirect.
     * Both GET and POST to church-info must be reachable so the user can
     * load and submit the form without being redirected in a loop.
     *
     * @var string[]
     */
    private const EXEMPT_PATHS = [
        self::SETUP_PATH,
        '/admin/system/church-info',
        '/admin/system/restore',
        LocalAuthentication::STAFF_PASSWORD_PATH,
        LocalAuthentication::STAFF_TWO_FACTOR_PATH,
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

        return (new Response())->withStatus(302)->withHeader('Location', $rootPath . self::SETUP_PATH);
    }
}
