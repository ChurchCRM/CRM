<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalMemberUser;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Auth\PortalSessionMiddleware;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lets a portal session with no staff session use the Volunteer v2 API that
 * the portal's volunteering and My Teams pages call (member sign-in design
 * §8.2). It runs after AuthMiddleware, which lets such a request through only
 * on `/api/ministries/`.
 *
 *  1. `PortalSessionMiddleware` checks the portal session as it does for
 *     `/api/portal`: blocks, session version, idle time.
 *  2. The coarse gate: `/api/ministries/me/` needs `portal.volunteer.use`;
 *     the rest needs a team to manage or to decide substitutes for.
 *  3. For this request the person is the API's current user, as a
 *     `PortalMemberUser`, so every per-record volunteer check applies to them
 *     unchanged and takes their team authority from the portal model.
 *
 * Staff sessions and API keys pass straight through.
 */
final class VolunteerPortalMemberMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            !self::isVolunteerApiPath($request)
            || $request->getHeaderLine('x-api-key') !== ''
            || AuthenticationManager::validateUserSessionIsActive(false)
            || !PortalSessionStore::has()
        ) {
            return $handler->handle($request);
        }

        $next = new class ($handler) implements RequestHandlerInterface {
            public function __construct(private readonly RequestHandlerInterface $handler)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $principal = PortalContext::principal();
                if (!$principal instanceof PortalPrincipal || !VolunteerPortalMemberMiddleware::mayUse($principal, $request)) {
                    return SlimUtils::renderErrorJSON(
                        new Response(),
                        gettext('This part of the Member Portal is not available to you.'),
                        [],
                        403
                    );
                }

                AuthenticationManager::actAsPortalMember(PortalMemberUser::forPerson($principal->personId));

                return $this->handler->handle($request);
            }
        };

        return (new PortalSessionMiddleware())->process($request, $next);
    }

    /** @internal */
    public static function mayUse(PortalPrincipal $principal, ServerRequestInterface $request): bool
    {
        $authz = new PortalAuthorizationService();
        if (self::isPathUnder($request, '/api/ministries/me/')) {
            return $authz->scopesFor($principal, CorePortalCapabilities::VOLUNTEER_USE) !== [];
        }

        return $authz->scopesFor($principal, CorePortalCapabilities::TEAM_MANAGE) !== []
            || $authz->scopesFor($principal, CorePortalCapabilities::TEAM_SUBSTITUTES) !== [];
    }

    public static function isVolunteerApiPath(ServerRequestInterface $request): bool
    {
        return self::isPathUnder($request, '/api/ministries/');
    }

    private static function isPathUnder(ServerRequestInterface $request, string $prefix): bool
    {
        return str_starts_with($request->getUri()->getPath(), SystemURLs::getRootPath() . $prefix);
    }
}
