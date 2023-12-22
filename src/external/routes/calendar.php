<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/calendars', function (RouteCollectorProxy $group): void {
    $group->get('/{CalendarAccessToken}', 'serveCalendarPage');
    $group->get('/{CalendarAccessToken}/', 'serveCalendarPage');
})->add(PublicCalendarAPIMiddleware::class);

function serveCalendarPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/calendar/');
    $eventSource = SystemURLs::getRootPath() . '/api/public/calendar/' . SlimUtils::getRouteArgument($request, 'CalendarAccessToken') . '/fullcalendar';
    $calendarName = $request->getAttribute('calendar')->getName();

    return $renderer->render($response, 'calendar.php', ['eventSource' => $eventSource, 'calendarName' => $calendarName]);
}
