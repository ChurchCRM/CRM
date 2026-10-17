<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
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

        // Portal pages carry the same security headers as the rest of the
        // application — the CSP nonce every inline script and theme.js uses —
        // and the plugin head/footer content the layout prints.
        PortalTwig::preparePage();

        // Every portal page acts for the session's own person and no other
        // (design P11). Resolving it once here is what lets a route read the
        // actor without ever taking an id from the request. It stays null for
        // an account with no person record; the pages that need one 404.
        $person = AuthenticationManager::getCurrentUser()->getPerson();

        $this->recordActivity();

        return $handler->handle($request->withAttribute(PortalSelfService::ACTOR_ATTRIBUTE, $person));
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
