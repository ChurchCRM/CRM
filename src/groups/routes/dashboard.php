<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /groups root to /groups/dashboard
$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/groups/dashboard')
        ->withStatus(302);
});

// Match /groups/dashboard - Groups Dashboard (replaces GroupList.php)
$app->get('/dashboard', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Group Listing'),
        'sPageSubtitle' => gettext('View and manage all groups in your congregation'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Groups')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Group Properties'), 'url' => '/PropertyList.php?Type=g', 'icon' => 'fa-list'],
            ['label' => gettext('Group Types'), 'url' => '/OptionManager.php?mode=grptypes', 'icon' => 'fa-tags'],
        ]),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
