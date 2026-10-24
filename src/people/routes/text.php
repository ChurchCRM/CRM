<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/text', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getPeopleTextDashboard');
    $group->get('', 'getPeopleTextDashboard');
    $group->get('/', 'getPeopleTextDashboard');
})->add(EmailRoleAuthMiddleware::class);

function getPeopleTextDashboard(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/text/');

    $vonagePlugin = PluginManager::getPlugin('vonage');

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Text'),
        'sPageSubtitle'      => gettext('Manage SMS/text messaging tools and settings'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Text')],
        ]),
        'sSettingsCollapseId' => 'textSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Text Settings'), 'collapse' => '#textSettings', 'icon' => 'fa-sliders'],
        ]),
        'vonageConfigured'   => $vonagePlugin !== null && $vonagePlugin->isConfigured(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
