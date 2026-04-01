<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/calendar', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getCalendar');
    $group->get('', 'getCalendar');
});

function getCalendar(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/calendar/');
    $isAdmin  = AuthenticationManager::getCurrentUser()->isAdmin();

    $headerButtons = [
        ['label' => gettext('Calendars'), 'icon' => 'fa-layer-group', 'offcanvas' => '#calendarSidebar', 'adminOnly' => false],
    ];
    if ($isAdmin) {
        $headerButtons[] = ['label' => gettext('Calendar Settings'), 'icon' => 'fa-sliders', 'collapse' => '#calendarSettings', 'adminOnly' => true];
    }

    $pageArgs = [
        'sRootPath'            => SystemURLs::getRootPath(),
        'sPageTitle'           => gettext('Calendar'),
        'sPageSubtitle'        => gettext('Manage events, birthdays, and anniversaries'),
        'aBreadcrumbs'         => PageHeader::breadcrumbs([
            [gettext('Calendar')],
        ]),
        'sPageHeaderButtons'   => PageHeader::buttons($headerButtons),
        'sSettingsCollapseId'  => $isAdmin ? 'calendarSettings' : '',
        'isAdmin'              => $isAdmin,
        'calendarJSArgs'       => getCalendarJSArgs(),
    ];

    return $renderer->render($response, 'calendar.php', $pageArgs);
}

function getCalendarJSArgs(): array
{
    return [
        'isModifiable'               => AuthenticationManager::getCurrentUser()->isAddEvent(),
        'countCalendarAccessTokens'  => CalendarQuery::create()->filterByAccessToken(null, Criteria::NOT_EQUAL)->count(),
        'bEnableExternalCalendarAPI' => SystemConfig::getBooleanValue('bEnableExternalCalendarAPI'),
        'sTimeZone'                  => SystemConfig::getValue('sTimeZone'),
    ];
}
