<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/calendars', function (RouteCollectorProxy $group) {
    $group->get('/{CalendarAccessToken}', 'serveCalendarPage');
    $group->get('/{CalendarAccessToken}/', 'serveCalendarPage');
})->add(PublicCalendarAPIMiddleware::class);

function serveCalendarPage($request, $response)
{
    $renderer = new PhpRenderer('templates/calendar/');
    $eventSource = SystemURLs::getRootPath() . '/api/public/calendar/' . $request->getAttribute('route')->getArgument('CalendarAccessToken') . '/fullcalendar';
    $calendarName = $request->getAttribute('calendar')->getName();

    return $renderer->render($response, 'calendar.php', ['eventSource' => $eventSource, 'calendarName' => $calendarName]);
}
