<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /groups root to /groups/dashboard
$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/groups/dashboard')
        ->withStatus(302);
});

// Match /groups/dashboard - Groups Dashboard (replaces GroupList.php)
$app->get('/dashboard', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    // Fetch group types (list option ID = 3).
    // Exclude Sunday School (option ID = 4) only when the SS module is disabled.
    $sundaySchoolEnabled = SystemConfig::getBooleanValue('bEnabledSundaySchool');
    $groupTypes = [];
    foreach (ListOptionQuery::create()->filterById(3)->orderByOptionSequence()->find() as $opt) {
        if ((int) $opt->getOptionId() === 4 && !$sundaySchoolEnabled) {
            continue;
        }
        $groupTypes[] = ['id' => (int) $opt->getOptionId(), 'name' => $opt->getOptionName()];
    }

    $currentUser = AuthenticationManager::getCurrentUser();
    $isAdmin     = $currentUser->isAdmin();

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Group Listing'),
        'sPageSubtitle'      => gettext('View and manage all groups in your congregation'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Groups')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons(array_filter([
            ['label' => gettext('Group Reports'), 'url' => '/groups/reports', 'icon' => 'fa-file-lines'],
            ['label' => gettext('Group Properties'), 'url' => '/PropertyList.php?Type=g', 'icon' => 'fa-list'],
            $isAdmin ? ['label' => gettext('Group Types'), 'url' => '/admin/system/options?mode=grptypes', 'icon' => 'fa-tags'] : null,
            $isAdmin ? ['label' => gettext('Group Settings'), 'collapse' => '#groupSettings', 'icon' => 'fa-sliders', 'adminOnly' => true] : null,
        ])),
        'sSettingsCollapseId' => $isAdmin ? 'groupSettings' : null,
        'groupTypes'          => $groupTypes,
        'isAdmin'             => $isAdmin,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
