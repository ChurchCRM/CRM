<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use Slim\Routing\RouteCollectorProxy;
$app->group('/calendar', function (RouteCollectorProxy $group) {
    $group->get('/', 'getCalendar');
    $group->get('', 'getCalendar');
});

function getCalendar(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/calendar/');

    $pageArgs = [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Calendar'),
        'calendarJSArgs' => getCalendarJSArgs(),
    ];

    return $renderer->render($response, 'calendar.php', $pageArgs);
}

function getCalendarJSArgs()
{
    return [
        'isModifiable'               => AuthenticationManager::getCurrentUser()->isAddEvent(),
        'countCalendarAccessTokens'  => CalendarQuery::create()->filterByAccessToken(null, Criteria::NOT_EQUAL)->count(),
        'bEnableExternalCalendarAPI' => SystemConfig::getBooleanValue('bEnableExternalCalendarAPI'),
    ];
}
