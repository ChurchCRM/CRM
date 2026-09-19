<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalCalendarSubscription;
use ChurchCRM\Portal\PortalSelfServiceException;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/calendar/*` — what the Member Portal's calendar page reads
 * (design §5.3, issue #9866).
 *
 * Three properties hold here, and they are why the portal does not simply call
 * `/api/calendars/{id}/fullcalendar` per calendar:
 *
 *  - **Session only.** `PortalApiMiddleware` refuses an API key outright, so
 *    this is not a second, key-shaped way into church events.
 *  - **The administrator chooses the calendars, not the caller.** There is no
 *    calendar id in the URL: the answer is the union of whatever
 *    Admin → Member Portal → Calendars has switched on. Asking for a calendar
 *    that is not shared is not a permission failure, it is unexpressible.
 *  - **A bounded window.** `from` and `to` are plain `YYYY-MM-DD` days, and the
 *    window is clamped to PortalCalendarService::MAX_WINDOW_DAYS, because the
 *    system calendars expand a row per person per year and an unbounded range
 *    would be a cheap way to make the server work hard.
 */
$app->group('/portal/calendar', function (RouteCollectorProxy $group): void {
    $group->get('/events', 'getPortalCalendarEventsAPI');
})->add(PortalApiMiddleware::class);

/*
 * `/api/portal/calendar/subscription` — the member's own iCalendar feed
 * (design §5.3, "Subscribing").
 *
 * Same gate as the rest of the portal (session only, actor from the session,
 * CSRF on every write) plus one rule of its own: a member may only ever tick a
 * calendar the administrator currently shares with members. The check lives in
 * `PortalCalendarSubscription::save()`, so neither this file nor the feed route
 * can forget it.
 *
 * What these three routes do *not* do is serve the calendar. That is
 * `/api/public/portal-calendar/{token}/calendar.ics`, which has no session at
 * all — a calendar app cannot sign in — and is documented in
 * `src/api/routes/public/public-portal-calendar.php`.
 */
$app->group('/portal/calendar/subscription', function (RouteCollectorProxy $group): void {
    $group->get('', 'getPortalCalendarSubscriptionAPI');
    $group->get('/', 'getPortalCalendarSubscriptionAPI');
    $group->put('', 'putPortalCalendarSubscriptionAPI');
    $group->put('/', 'putPortalCalendarSubscriptionAPI');
    $group->post('/reset', 'resetPortalCalendarSubscriptionAPI');
})->add(new CSRFMiddleware())->add(new PortalApiMiddleware());

/**
 * GET /api/portal/calendar/events?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Answers `{from, to, events}`: the effective window (which is not necessarily
 * the one that was asked for — see the clamp) and the events on it, already
 * shaped for FullCalendar in the church's configured timezone.
 *
 *   400 — `from` or `to` is not a calendar day, or `to` is before `from`
 */
function getPortalCalendarEventsAPI(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();

    // `today` is midnight in the configured sTimeZone: Bootstrapper makes it
    // PHP's default timezone, so "today" means today at the church.
    $from = new DateTimeImmutable('today');
    if (isset($params['from'])) {
        $from = portalCalendarDay($params['from']);
        if ($from === null) {
            return portalCalendarBadRequest($request, $response, gettext('The start of the range is not a date.'));
        }
    }

    $to = $from->modify('+31 days');
    if (isset($params['to'])) {
        $to = portalCalendarDay($params['to']);
        if ($to === null) {
            return portalCalendarBadRequest($request, $response, gettext('The end of the range is not a date.'));
        }
    }

    if ($to < $from) {
        return portalCalendarBadRequest($request, $response, gettext('The end of the range is before its start.'));
    }

    // Clamp rather than refuse: a client asking for a year still gets a useful
    // answer, and the response says which window it actually describes.
    $latest = $from->modify('+' . PortalCalendarService::MAX_WINDOW_DAYS . ' days');
    if ($to > $latest) {
        $to = $latest;
    }

    return SlimUtils::renderJSON($response, [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'hasVisibleCalendars' => PortalCalendarService::hasVisibleCalendars(),
        'events' => PortalCalendarService::eventsBetween($from, $to),
    ]);
}

/**
 * A `YYYY-MM-DD` query parameter as a day, or null when it is absent or is not
 * a real calendar day. Strict on purpose: the portal calendar sends days, and
 * anything looser would let a caller smuggle a time zone in.
 */
function portalCalendarDay(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $day === false || $day->format('Y-m-d') !== $value ? null : $day;
}

function portalCalendarBadRequest(Request $request, Response $response, string $message): Response
{
    return SlimUtils::renderErrorJSON($response, $message, [], 400, null, $request);
}

/**
 * @OA\Get(
 *     path="/portal/calendar/subscription",
 *     operationId="getPortalCalendarSubscription",
 *     summary="The signed-in member's calendar subscription",
 *     description="Returns the calendars the church shares with members, which of them this member has subscribed to, and the address their calendar app should use. `url` and `webcalUrl` are null until the member saves a selection for the first time — the token is minted then. Session only; API keys are refused.",
 *     tags={"Member Portal"},
 *     @OA\Response(response=200, description="The member's subscription", @OA\JsonContent(ref="#/components/schemas/PortalCalendarSubscription")),
 *     @OA\Response(response=403, description="Not a signed-in member, or an API key was used")
 * )
 */
function getPortalCalendarSubscriptionAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, portalCalendarSubscriptionPayload($request));
}

/**
 * @OA\Put(
 *     path="/portal/calendar/subscription",
 *     operationId="putPortalCalendarSubscription",
 *     summary="Choose which shared calendars the member's feed carries",
 *     description="Stores the member's selection and mints their feed token the first time. Every id must name a calendar the administrator currently shares with members; anything else is a 400, with the same message whether the calendar does not exist or is simply not shared. At least one calendar is required — a feed of nothing is not a useful thing to hand a calendar app.",
 *     tags={"Member Portal"},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="calendars", type="array", description="Calendar ids, as GET returns them", @OA\Items(type="string", example="calendar:3"))
 *     )),
 *     @OA\Response(response=200, description="The saved subscription", @OA\JsonContent(ref="#/components/schemas/PortalCalendarSubscription")),
 *     @OA\Response(response=400, description="Empty selection, or a calendar that is not shared with members"),
 *     @OA\Response(response=403, description="Not a signed-in member, an API key was used, or the CSRF token was missing")
 * )
 */
function putPortalCalendarSubscriptionAPI(Request $request, Response $response): Response
{
    $user = portalCalendarSubscriptionUser($request);
    $body = $request->getParsedBody();
    $calendars = is_array($body) && isset($body['calendars']) && is_array($body['calendars'])
        ? $body['calendars']
        : [];

    // The list is not run through InputSanitizationMiddleware: that middleware
    // cleans scalar fields, and the only field here is a list. Sanitizing each
    // entry keeps stray markup out of anything that might echo it back, and the
    // real check is the one below — every id has to match a calendar the church
    // shares right now, which is strictly stronger than sanitizing.
    $calendars = array_map(
        static fn (mixed $id): mixed => is_string($id) ? InputUtils::sanitizeText($id) : $id,
        $calendars
    );

    try {
        PortalCalendarSubscription::save($user, $calendars);
    } catch (PortalSelfServiceException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->getStatus(), null, $request);
    }

    return SlimUtils::renderJSON($response, portalCalendarSubscriptionPayload($request));
}

/**
 * @OA\Post(
 *     path="/portal/calendar/subscription/reset",
 *     operationId="resetPortalCalendarSubscription",
 *     summary="Issue the member a new feed address",
 *     description="Rotates the member's feed token, keeping their calendar selection. The previous address stops answering immediately — this is what a member does when they have shared the old one by accident.",
 *     tags={"Member Portal"},
 *     @OA\Response(response=200, description="The subscription, at its new address", @OA\JsonContent(ref="#/components/schemas/PortalCalendarSubscription")),
 *     @OA\Response(response=403, description="Not a signed-in member, an API key was used, or the CSRF token was missing")
 * )
 */
function resetPortalCalendarSubscriptionAPI(Request $request, Response $response): Response
{
    PortalCalendarSubscription::reset(portalCalendarSubscriptionUser($request));

    return SlimUtils::renderJSON($response, portalCalendarSubscriptionPayload($request));
}

/**
 * @OA\Schema(
 *     schema="PortalCalendarSubscription",
 *     type="object",
 *     @OA\Property(property="title", type="string", example="Main St. Cathedral Calendar", description="The name a calendar app shows for the feed"),
 *     @OA\Property(property="url", type="string", nullable=true, example="https://church.example/api/public/portal-calendar/ab12…/calendar.ics"),
 *     @OA\Property(property="webcalUrl", type="string", nullable=true, example="webcal://church.example/api/public/portal-calendar/ab12…/calendar.ics"),
 *     @OA\Property(property="choices", type="array", @OA\Items(type="object",
 *         @OA\Property(property="id", type="string", example="calendar:3"),
 *         @OA\Property(property="name", type="string", example="Public Calendar"),
 *         @OA\Property(property="color", type="string", example="#2c3e50"),
 *         @OA\Property(property="selected", type="boolean")
 *     ))
 * )
 *
 * The answer all three routes give, so a client never has to guess what a save
 * changed.
 *
 * @return array<string, mixed>
 */
function portalCalendarSubscriptionPayload(Request $request): array
{
    $user = portalCalendarSubscriptionUser($request);
    $path = PortalCalendarSubscription::feedPath($user);
    $url = $path === null ? null : portalCalendarAbsoluteUrl($request, $path);

    return [
        'title' => PortalCalendarSubscription::title(),
        'url' => $url,
        // webcal:// is what makes "Open in calendar app" a single tap: macOS,
        // iOS, Outlook and Google all register the scheme and open their
        // "subscribe to a calendar" flow rather than downloading a file once.
        // It is still sent over plain http, but the portal does not offer the
        // link there: Apple's clients rewrite webcal:// to https:// and never
        // fall back, so the tap would fail silently. See
        // webpack/portal/portal-calendar-subscribe.ts.
        'webcalUrl' => $url === null ? null : preg_replace('#^https?://#', 'webcal://', $url),
        'choices' => PortalCalendarSubscription::choices($user),
    ];
}

/**
 * The signed-in member's account. `PortalApiMiddleware` has already refused
 * anonymous and API-key callers, so this cannot be null by the time a handler
 * runs; the fallback keeps static analysis honest.
 */
function portalCalendarSubscriptionUser(Request $request): User
{
    $user = AuthenticationManager::getCurrentUser();
    if (!$user instanceof User) {
        throw new HttpForbiddenException($request, gettext('No logged in user'));
    }

    return $user;
}

/**
 * An absolute URL for a path on this installation.
 *
 * Built from the request rather than from `SystemURLs::getURL()` on purpose: a
 * member is told to paste this address into a phone, and the host they are
 * browsing on is the one that demonstrably reaches the server. The configured
 * canonical URL is often a bare domain that does not carry the port or the
 * hostname a church actually uses inside its own network.
 */
function portalCalendarAbsoluteUrl(Request $request, string $path): string
{
    $uri = $request->getUri();
    $authority = $uri->getHost();
    $port = $uri->getPort();
    if ($port !== null && $port !== 80 && $port !== 443) {
        $authority .= ':' . $port;
    }

    return $uri->getScheme() . '://' . $authority . SystemURLs::getRootPath() . $path;
}
