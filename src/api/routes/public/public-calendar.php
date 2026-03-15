<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\iCal;
use ChurchCRM\Slim\Middleware\Api\PublicCalendarMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/calendar', function (RouteCollectorProxy $group): void {
    $group->get('/{CalendarAccessToken}/events', 'getJSON');
    $group->get('/{CalendarAccessToken}/ics', 'getICal');
    $group->get('/{CalendarAccessToken}/fullcalendar', 'getPublicCalendarFullCalendarEvents');
})->add(PublicCalendarMiddleware::class);

/**
 * @OA\Get(
 *     path="/public/calendar/{CalendarAccessToken}/events",
 *     operationId="getPublicCalendarEvents",
 *     summary="Get public calendar events as JSON",
 *     description="Returns events for a publicly shared calendar using its access token.",
 *     tags={"Calendar"},
 *     @OA\Parameter(
 *         name="CalendarAccessToken",
 *         in="path",
 *         required=true,
 *         description="The calendar's public access token (found in calendar settings)",
 *         @OA\Schema(type="string", example="abc123token")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of event objects",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="Id", type="integer"),
 *                 @OA\Property(property="Title", type="string"),
 *                 @OA\Property(property="Start", type="string", format="date-time"),
 *                 @OA\Property(property="End", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Invalid or expired access token")
 * )
 */
function getJSON(Request $request, Response $response): Response
{
    $events = $request->getAttribute('events');

    return SlimUtils::renderJSON($response, $events->toArray());
}

/**
 * @OA\Get(
 *     path="/public/calendar/{CalendarAccessToken}/ics",
 *     operationId="getPublicCalendarICS",
 *     summary="Download public calendar as ICS file",
 *     description="Returns an iCalendar (.ics) file for the publicly shared calendar, suitable for import into calendar apps.",
 *     tags={"Calendar"},
 *     @OA\Parameter(
 *         name="CalendarAccessToken",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", example="abc123token")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="iCalendar file",
 *         @OA\MediaType(mediaType="text/calendar", @OA\Schema(type="string", format="binary"))
 *     ),
 *     @OA\Response(response=404, description="Invalid or expired access token")
 * )
 */
function getICal($request, $response)
{
    $calendar = $request->getAttribute('calendar');
    $events = $request->getAttribute('events');
    $calendarName = $calendar->getName() . ': ' . ChurchMetaData::getChurchName();
    $CalendarICS = new iCal($events, $calendarName);
    $body = $response->getBody();
    $body->write($CalendarICS->toString());

    return $response->withHeader('Content-type', 'text/calendar; charset=utf-8')
        ->withHeader('Content-Disposition', 'attachment; filename=calendar.ics');
}

/**
 * @OA\Get(
 *     path="/public/calendar/{CalendarAccessToken}/fullcalendar",
 *     operationId="getPublicCalendarFullCalendarEvents",
 *     summary="Get public calendar events in FullCalendar format",
 *     description="Returns events formatted for the FullCalendar JavaScript library.",
 *     tags={"Calendar"},
 *     @OA\Parameter(
 *         name="CalendarAccessToken",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", example="abc123token")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="FullCalendar-compatible event array",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="title", type="string"),
 *                 @OA\Property(property="start", type="string", format="date-time"),
 *                 @OA\Property(property="end", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Invalid or expired access token")
 * )
 */
function getPublicCalendarFullCalendarEvents($request, Response $response): Response
{
    $calendar = $request->getAttribute('calendar');
    $events = $request->getAttribute('events');

    return SlimUtils::renderJSON($response, EventsObjectCollectionToFullCalendar($events, $calendar));
}
