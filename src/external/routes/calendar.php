<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;
use Slim\Views\PhpRenderer;

$app->group('/calendars', function () use ($app) {
    $app->get('/{CalendarAccessToken}', 'serveCalendarPage');
    $app->get('/{CalendarAccessToken}/', 'serveCalendarPage');
})->add(new PublicCalendarAPIMiddleware());

function serveCalendarPage($request, $response)
{
    $renderer = new PhpRenderer('templates/calendar/');
    $eventSource = SystemURLs::getRootPath().'/api/public/calendar/'.$request->getAttribute('route')->getArgument('CalendarAccessToken').'/fullcalendar';
    $calendarName = $request->getAttribute('calendar')->getName();

    return $renderer->render($response, 'calendar.php', ['eventSource' => $eventSource, 'calendarName' => $calendarName]);
}
