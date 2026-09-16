<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Throwable;

/**
 * The gate on every Member Portal page: an authenticated browser session, and
 * nothing else.
 *
 * API-key callers are refused outright. The portal is a rendered, session-bound
 * surface whose every page derives the acting person from the session (decision
 * P11); letting a key in would create a second way to reach member data that
 * does not go through `/api/portal/*`.
 *
 * The public theme-asset route is deliberately *not* behind this middleware —
 * a cached page must keep loading its stylesheet after the session has expired.
 */
class PortalAccessMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('x-api-key') !== '') {
            throw new HttpForbiddenException(
                $request,
                gettext('The Member Portal is only available to a signed-in person, not to API clients.')
            );
        }

        if (!AuthenticationManager::validateUserSessionIsActive(true)) {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', AuthenticationManager::getSessionBeginURL());
        }

        $documentRoot = rtrim(SystemURLs::getDocumentRoot(), '/\\');

        // Portal pages carry the same security headers as the rest of the
        // application — the CSP nonce every inline script and theme.js uses is
        // emitted here.
        require_once $documentRoot . '/Include/Header-Security.php';

        // Active plugins inject <head> and footer content into portal pages the
        // same way they do into admin pages; the layout prints what they
        // return. init() is idempotent, exactly as PageInit.php relies on.
        PluginManager::init($documentRoot . '/plugins');

        $this->recordActivity();

        return $handler->handle($request);
    }

    /**
     * Stamp `usr_LastPortalActivity` so the Admin → Member Portal statistics
     * tab can answer "active in the last 15 minutes" honestly (#9864).
     *
     * The write is throttled to once every five minutes by comparing the value
     * already in the column, not a session flag, so it survives session churn
     * and costs one small UPDATE per member per five minutes at most. A failure
     * here must never break a portal page: the column is a statistic.
     */
    private function recordActivity(): void
    {
        $user = AuthenticationManager::getCurrentUser();
        if (!$user instanceof User) {
            return;
        }

        try {
            PortalStatsService::recordPortalActivity($user);
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Could not record Member Portal activity', [
                'userName' => $user->getUserName(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
