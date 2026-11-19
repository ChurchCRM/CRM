<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\UpgradeAPIService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/community-feedback', 'viewCommunityFeedback');
$app->get('/access-denied', 'viewAccessDenied');
$app->get('/releases', 'viewReleases');
$app->post('/releases/dismiss-update-notice', 'dismissUpdateNotice')->add(new CSRFMiddleware());

function viewAccessDenied(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    // Allowed role codes that can be displayed on the access-denied page
    $allowedRoles = [
        'Admin',
        'Finance',
        'ManageGroups',
        'EditRecords',
        'DeleteRecords',
        'AddRecords',
        'Notes',
        'AddEvent',
        'Authentication',
        // Volunteer Management v2 (#9704). The role middlewares that emit these
        // arrive with #9706; listing them now keeps the access-denied page from
        // silently blanking the role name when they do.
        'ManageMinistries',
        'VolunteerCoordinator',
    ];

    $queryParams = $request->getQueryParams();
    $missingRole = in_array($queryParams['role'] ?? '', $allowedRoles, true)
        ? $queryParams['role']
        : '';

    $pageArgs = [
        'sRootPath'   => SystemURLs::getRootPath(),
        'sPageTitle'  => gettext('Access Denied'),
        'missingRole' => $missingRole,
    ];

    return $renderer->render($response, 'access-denied.php', $pageArgs);
}

function viewCommunityFeedback(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');

    $pageArgs = [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Review what we are considering'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('Review what we are considering')],
        ]),
        'sDiscussionsUrl' => 'https://github.com/ChurchCRM/CRM/discussions',
    ];

    return $renderer->render($response, 'community-feedback.php', $pageArgs);
}

/**
 * Read-only release history for any authenticated staff member.
 * Uses the same preview data as the admin upgrade wizard.
 */
function viewReleases(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');
    try {
        $preview = UpgradeAPIService::getUpgradePreview();
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->warning('Release history unavailable', ['error' => $e->getMessage()]);
        $preview = [
            'installedVersion' => '',
            'upgradePath' => [],
            'unavailable' => true,
        ];
    }

    return $renderer->render($response, 'releases.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('What\'s New'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([[gettext('Releases')]]),
        'preview' => $preview,
    ]);
}

/** Dismiss only the system-update notice until this staff session ends. */
function dismissUpdateNotice(Request $request, Response $response, array $args): Response
{
    $_SESSION['systemUpdateNoticeDismissed'] = true;
    return $response->withStatus(204);
}
