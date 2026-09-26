<?php

use ChurchCRM\Portal\PortalCalendarFeed;
use ChurchCRM\Portal\PortalCalendarSubscription;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/public/portal-calendar/{token}/calendar.ics` — the iCalendar feed a
 * member subscribes their own calendar app to (design §5.3, "Subscribing").
 *
 * This is the only part of the Member Portal that answers without a session,
 * and it has to be: a calendar app is not a browser, it holds no cookie and it
 * cannot be asked to sign in. `AuthMiddleware::isPublicPath()` lets
 * `/api/public/*` through, so the token in the URL is the entire credential.
 *
 * Security
 * --------
 * The token is a **bearer secret**: 32 random bytes as hex, minted when the
 * member first saves a selection and rotated by "Reset link" on the portal
 * calendar page, which retires the old address at once. Holding it is
 * equivalent to read access to the calendars that member ticked — including
 * the virtual Birthdays and Anniversaries calendars when an administrator has
 * chosen to share those, which is exactly why that choice is deliberate and
 * per-church. Four properties bound what a leaked token is worth:
 *
 *  1. **Only shared calendars, only right now.** The feed is the member's
 *     selection intersected with the calendars Admin → Member Portal currently
 *     shares (`PortalCalendarSubscription::events()`). Un-sharing a calendar
 *     empties it out of every member's feed on the next fetch, without any
 *     member re-saving anything, and a selection that names a calendar which
 *     has since been un-shared serves nothing from it.
 *  2. **Nothing else about the member is reachable.** No name, no family, no
 *     profile, no account state — just the events, already shaped and
 *     privacy-trimmed by `PortalCalendarService` (a birthday reads as
 *     "Lena B.", never a surname and never an age).
 *  3. **An unknown token is indistinguishable from a member without a feed.**
 *     `findByToken()` answers null for both, and both become the same bare 404,
 *     so the route cannot be used to confirm that any particular token, or any
 *     particular account, exists.
 *  4. **The token is never logged.** Nothing here writes the path or the token
 *     to the application log; a feed URL in a log file would be the credential
 *     itself, readable by every member of staff.
 *
 * The response is not cached by intermediaries (`private, no-cache`): the body
 * is one member's calendar and must not sit in a shared cache.
 */
$app->group('/public/portal-calendar', function (RouteCollectorProxy $group): void {
    $group->get('/{token}/calendar.ics', 'getPortalCalendarFeed');
});

/**
 * @OA\Get(
 *     path="/public/portal-calendar/{token}/calendar.ics",
 *     operationId="getPortalCalendarFeed",
 *     summary="A member's Member Portal calendar feed (iCalendar)",
 *     description="Serves the events of the calendars a member subscribed to in the Member Portal, as an RFC 5545 VCALENDAR covering three months back to eighteen months ahead. No session: the token in the path is the credential, and it grants read access to those calendars and to nothing else. The feed is always narrowed to the calendars the administrator currently shares with members, so un-sharing a calendar removes it from every feed immediately. An unknown or retired token is a bare 404, indistinguishable from an account that has no feed.",
 *     tags={"Member Portal"},
 *     @OA\Parameter(
 *         name="token",
 *         in="path",
 *         required=true,
 *         description="The member's feed token, 64 hex characters, from the Subscribe dialog on the portal calendar page",
 *         @OA\Schema(type="string", example="4f3c…")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="An iCalendar document",
 *         @OA\MediaType(mediaType="text/calendar", @OA\Schema(type="string", format="binary"))
 *     ),
 *     @OA\Response(response=404, description="Unknown token, a token that has been reset, or an account with no feed")
 * )
 */
function getPortalCalendarFeed(Request $request, Response $response, array $args): Response
{
    $user = PortalCalendarSubscription::findByToken((string) ($args['token'] ?? ''));
    if ($user === null) {
        // Deliberately the framework's own generic 404 — no message naming a
        // member, a token or a reason, and the same answer for every kind of
        // miss.
        throw new HttpNotFoundException($request);
    }

    $document = PortalCalendarFeed::build(
        PortalCalendarSubscription::events($user),
        PortalCalendarSubscription::title(),
        $request->getUri()->getHost()
    );

    $response->getBody()->write($document);

    return $response
        ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
        // `inline`, not `attachment`: a member who opens the address in a
        // browser should see the calendar, and a calendar app that subscribes
        // ignores the header either way.
        ->withHeader('Content-Disposition', 'inline; filename="calendar.ics"')
        ->withHeader('Cache-Control', 'private, no-cache');
}
