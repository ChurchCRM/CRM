<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use ChurchCRM\Volunteer\Middleware\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * S3 — ministry detail (#9715, design §5.4).
 *
 * A tabbed page whose tabs load lazily on activation, the
 * `webpack/people/attendance-history.ts` pattern (U6). This issue ships
 * **Overview**, **Teams** and **Positions**; #9707 adds Pools and
 * Qualifications and #9708/#9711 add Schedules, by appending one `<li>` to the
 * tab strip and one pane to the tab content — see the markers in
 * `views/ministry-view.php`.
 *
 * The role middleware below answers "does this person coordinate anything";
 * **which** ministry they may open is a per-record question the middleware
 * cannot answer, so the handler asks `VolunteerAuthorizationService` directly
 * (design §4.5, layer three). A ministry that does not exist is a 404; one
 * outside the caller's scope goes to the access-denied page, the same place
 * `BaseAuthRoleMiddleware` sends a browser it turns away.
 *
 * Route paths are module-relative: setBasePath() already carries '/ministries'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /ministries/{ministryId}
    $group->get('/{ministryId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $ministryId = (int) $args['ministryId'];
        $ministry = VolunteerMinistryQuery::create()->findPk($ministryId);

        if ($ministry === null) {
            throw new HttpNotFoundException($request, gettext('Ministry not found'));
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();
        // Coordinator-or-above for THIS ministry: true for an administrator, a global
        // volunteer manager and the holder of a ministry scope, false for a team
        // leader. It is handed to the page as an ADVISORY flag — it decides whether
        // "Remove Volunteer" is offered, never whether it is allowed. The API decides
        // that independently, with the ministry-level entity middleware (D5, §4.5).
        $bIsMinistryCoordinator = $authz->canManageMinistry($currentUser, $ministryId);

        if (!$bIsMinistryCoordinator) {
            return SlimUtils::renderRedirect(
                $response,
                SystemURLs::getRootPath() . '/v2/access-denied?role=VolunteerCoordinator'
            );
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'ministry-view.php', [
            'sRootPath'      => SystemURLs::getRootPath(),
            'sPageTitle'     => $ministry->getName(),
            'sPageSubtitle'  => $ministry->getDescription() ?? gettext('Teams, positions and the people who fill them'),
            'aBreadcrumbs'   => PageHeader::breadcrumbs([
                [gettext('Ministries'), '/ministries/dashboard'],
                [$ministry->getName()],
            ]),
            'iMinistryId'    => $ministryId,
            'sMinistryName'  => $ministry->getName(),
            'bMinistryActive' => (bool) $ministry->getActive(),
            'bIsManager'     => $authz->isGlobalManager($currentUser),
            'bIsMinistryCoordinator' => $bIsMinistryCoordinator,
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
