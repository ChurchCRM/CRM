<?php

namespace ChurchCRM\Slim\Middleware;

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
 * When sChurchName is empty (fresh install), all browser requests to the
 * admin module are redirected to the church-info setup page. API routes
 * and the church-info page itself are exempt from the redirect so the
 * administrator can fill in the required information.
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
