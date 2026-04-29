<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/calendars — display the calendar page
$app->get('/calendars', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $isAdmin  = AuthenticationManager::getCurrentUser()->isAdmin();

    $headerButtons = [
        ['label' => gettext('Calendars'), 'icon' => 'fa-layer-group', 'offcanvas' => '#calendarSidebar', 'adminOnly' => false],
    ];
    if ($isAdmin) {
        $headerButtons[] = ['label' => gettext('Calendar Settings'), 'icon' => 'fa-sliders', 'collapse' => '#calendarSettings', 'adminOnly' => true];
        $headerButtons[] = ['label' => gettext('Holiday Settings'), 'icon' => 'fa-calendar-days', 'url' => '/plugins/management/holidays', 'adminOnly' => true];
    }

    $calendarJSArgs = [
        'isModifiable'               => AuthenticationManager::getCurrentUser()->isAddEvent(),
        'countCalendarAccessTokens'  => CalendarQuery::create()->filterByAccessToken(null, Criteria::NOT_EQUAL)->count(),
        'bEnableExternalCalendarAPI' => SystemConfig::getBooleanValue('bEnableExternalCalendarAPI'),
        'sTimeZone'                  => SystemConfig::getValue('sTimeZone'),
    ];

    return $renderer->render($response, 'calendar.php', [
        'sRootPath'            => SystemURLs::getRootPath(),
        'sPageTitle'           => gettext('Calendar'),
        'sPageSubtitle'        => gettext('Manage events, birthdays, and anniversaries'),
        'aBreadcrumbs'         => PageHeader::breadcrumbs([
            [gettext('Calendar')],
        ]),
        'sPageHeaderButtons'   => PageHeader::buttons($headerButtons),
        'sSettingsCollapseId'  => $isAdmin ? 'calendarSettings' : '',
        'isAdmin'              => $isAdmin,
        'calendarJSArgs'       => $calendarJSArgs,
    ]);
});
