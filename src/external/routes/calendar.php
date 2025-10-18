<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Api\PublicCalendarMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/calendars', function (RouteCollectorProxy $group): void {
    $group->get('/{CalendarAccessToken}', 'serveCalendarPage');
    $group->get('/{CalendarAccessToken}/', 'serveCalendarPage');
})->add(PublicCalendarMiddleware::class);

function serveCalendarPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/calendar/');
    $eventSource = SystemURLs::getRootPath() . '/api/public/calendar/' . SlimUtils::getRouteArgument($request, 'CalendarAccessToken') . '/fullcalendar';
    $calendarName = $request->getAttribute('calendar')->getName();

    return $renderer->render($response, 'calendar.php', ['eventSource' => $eventSource, 'calendarName' => $calendarName]);
}
