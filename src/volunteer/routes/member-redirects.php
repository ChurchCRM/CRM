<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * The old homes of S5 and S6 (#9712), kept as redirects for one release (MP6,
 * #9867; Member Portal design §5.4).
 *
 * The two member pages now live in the Member Portal — `/portal/volunteer/schedule`
 * and `/portal/volunteer/opportunities` — because a member must never see the
 * admin shell (design §0.2). What is left here is the forwarding address:
 * bookmarks, links in already-sent assignment and reminder mail, and any church
 * newsletter that quoted the old URL keep working.
 *
 * They stay inside the module's rollout group, so with `sVolunteerVersion` on
 * `v1` they behave exactly as the pages did — `VolunteerV2EnabledMiddleware`
 * turns the request away before the redirect is reached.
 *
 * **One honest limitation.** `AuthMiddleware::isLimitedAccessAllowedPath()` no
 * longer names these two paths, so a self-service login following an old link is
 * redirected to `/portal/` by that middleware before it gets here, rather than
 * to the page itself. They still land in the portal, one click from their
 * schedule; staff logins get the exact page. Removing the exemption is the point
 * of MP6 — the portal paths are what an EditSelf session may reach now.
 *
 * Route paths are module-relative: `setBasePath()` already carries '/volunteer'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    $redirectTo = static function (string $portalPath): callable {
        return static function (Request $request, Response $response) use ($portalPath): Response {
            return $response
                ->withStatus(302)
                ->withHeader('Location', SystemURLs::getRootPath() . $portalPath);
        };
    };

    $group->get('/my-schedule', $redirectTo('/portal/volunteer/schedule'));
    $group->get('/opportunities', $redirectTo('/portal/volunteer/opportunities'));
});
