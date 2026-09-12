<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * S2 — the guided setup flow (#9715, design §5.3).
 *
 * "Set it up once, then let the system run the weekly process" (§0.3) starts
 * here, and the issue's own UX requirement is that it guide a coordinator
 * through the hierarchy rather than present a collection of disconnected CRUD
 * pages. So this is one page with one thread through it: name the ministry, add
 * the team, list the positions.
 *
 * Who sees which step is decided server-side and sent to the view as page args:
 * creating a ministry is manager-only (§4.6), so a ministry coordinator opens the
 * flow already holding a ministry and starts at the team step. The view renders
 * the ministry step read-only for them, with their ministries to choose from —
 * it does not merely hide the button, because hiding is not security (D5) and
 * the API would refuse the call anyway.
 *
 * Route paths are module-relative: setBasePath() already carries '/volunteer'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /volunteer/setup — the guided flow. ?ministryId= resumes mid-flow, which
    // is how the ministry page's "add a team" and "add a position" links land here.
    $group->get('/setup', function (Request $request, Response $response): Response {
        $currentUser = AuthenticationManager::getCurrentUser();
        $service = new VolunteerSetupService();
        $isManager = $service->getAuthorizationService()->isGlobalManager($currentUser);

        $ministries = array_map(
            static fn (VolunteerMinistry $ministry): array => [
                'id' => (int) $ministry->getId(),
                'name' => $ministry->getName(),
            ],
            $service->listMinistriesFor($currentUser, true)
        );

        // Resume only into a ministry the caller actually holds — the id comes
        // from the query string, so it is never trusted on its own.
        $requestedId = (int) ($request->getQueryParams()['ministryId'] ?? 0);
        $resumeMinistryId = 0;
        foreach ($ministries as $ministry) {
            if ($ministry['id'] === $requestedId) {
                $resumeMinistryId = $requestedId;
                break;
            }
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'setup.php', [
            'sRootPath'        => SystemURLs::getRootPath(),
            'sPageTitle'       => gettext('Set up volunteer scheduling'),
            'sPageSubtitle'    => gettext('Create a ministry, add the teams that serve in it, then list the positions people fill'),
            'aBreadcrumbs'     => PageHeader::breadcrumbs([
                [gettext('Volunteer'), 'volunteer/dashboard'],
                [gettext('Setup')],
            ]),
            'bIsManager'       => $isManager,
            'aMinistries'      => $ministries,
            'iResumeMinistry'  => $resumeMinistryId,
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
