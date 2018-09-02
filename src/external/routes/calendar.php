<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;

$app->group('/calendars', function () {
    $this->get('/{CalendarAccessToken}', 'serveCalendarPage');
    $this->get('/{CalendarAccessToken}/', 'serveCalendarPage');

})->add(new PublicCalendarAPIMiddleware());

function serveCalendarPage ($request, $response) {
  $renderer = new PhpRenderer('templates/calendar/');
  $eventSource = SystemURLs::getRootPath()."/api/public/calendar/".$request->getAttribute("route")->getArgument("CalendarAccessToken")."/fullcalendar";
  $calendarName = $request->getAttribute("calendar")->getName();
  return $renderer->render($response, 'calendar.php', ['eventSource' => $eventSource, 'calendarName'=> $calendarName]);
}