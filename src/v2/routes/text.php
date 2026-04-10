<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/text', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getTextDashboardMVC');
    $group->get('', 'getTextDashboardMVC');
    $group->get('/', 'getTextDashboardMVC');
});

function getTextDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/text/');

    $vonagePlugin = PluginManager::getPlugin('vonage');
    $vonageConfigured = $vonagePlugin !== null && $vonagePlugin->isConfigured();

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Text Dashboard'),
        'sPageSubtitle' => gettext('Manage SMS/text messaging tools and settings'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Communication')],
            [gettext('Text')],
        ]),
        'sSettingsCollapseId' => 'textSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Text Settings'), 'collapse' => '#textSettings', 'icon' => 'fa-sliders', 'adminOnly' => true],
        ]),
        'vonageConfigured' => $vonageConfigured,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
