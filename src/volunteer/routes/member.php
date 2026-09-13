<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * S5 and S6 — the volunteer's own two pages (#9712, design §5.6).
 *
 * **This group carries NO role gate, deliberately.** Design §3.2 puts
 * `/volunteer/my-schedule` and `/volunteer/opportunities` in the "no role gate —
 * per-record authorization only, by authenticated person (D14)" row, and that is the
 * whole point: every authenticated person is potentially a volunteer, so a gate here
 * would lock out exactly the people the pages are for. The rollout flag still applies —
 * `VolunteerV2EnabledMiddleware` wraps the whole module in `index.php` — and so does
 * `AuthMiddleware`, whose #9706 exemption (§4.7) is what lets an EditSelf-exclusive
 * member reach these two paths and nothing else in the module.
 *
 * The pages themselves are markup plus a page config. Nothing is authorized here
 * because there is nothing here to authorize: both pages render an empty shell and
 * fetch everything from `/api/volunteer/me/*`, where the acting person is the session
 * and no route accepts a `personId`. A volunteer who edits the URL sees their own data,
 * because there is no id in the URL to edit.
 *
 * Route paths are module-relative: `setBasePath()` already carries '/volunteer'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /volunteer/my-schedule — S5.
    $group->get('/my-schedule', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'my-schedule.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('My Volunteer Schedule'),
            'sPageSubtitle' => gettext('What you are serving on next'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([[gettext('My Volunteer Schedule')]]),
            'sOpportunitiesUrl' => SystemURLs::getRootPath() . '/volunteer/opportunities',
        ]);
    });

    // GET /volunteer/opportunities — S6.
    $group->get('/opportunities', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'opportunities.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Open Opportunities'),
            'sPageSubtitle' => gettext('Places that still need someone'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('My Volunteer Schedule'), '/volunteer/my-schedule'],
                [gettext('Open Opportunities')],
            ]),
            'sMyScheduleUrl' => SystemURLs::getRootPath() . '/volunteer/my-schedule',
        ]);
    });
});
