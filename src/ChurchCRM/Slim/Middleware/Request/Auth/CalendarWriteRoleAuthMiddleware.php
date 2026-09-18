<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\Middleware\BrowserRequestTrait;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The write gate for ONE calendar (Member Portal design §5.3, Volunteer v2 D9/D19).
 *
 * `AddEventsRoleAuthMiddleware` answers a global question and is the right gate for
 * creating a calendar, which belongs to nobody yet. Administering an EXISTING calendar is
 * a per-row question once ministries own calendars, and this middleware is the answer:
 *
 *     allowed = canManageEvents()                                   // the global right, unchanged
 *            || (rollout on && calendar.ministry_id !== null
 *                           && canManageMinistry(user, calendar.ministry_id))
 *
 * It is the same rule `eventCalendarPinAllowed()` applies to pinning, in the same words,
 * so a coordinator who may pin to their ministry's calendar may also publish and unpublish
 * it — and may still touch no other calendar in the church.
 *
 * **Chain it AFTER `CalendarMiddleware`**, which means listing it FIRST in the `->add()`
 * chain: Slim runs the last-added middleware first, and this one needs the `calendar`
 * attribute that `CalendarMiddleware` puts on the request.
 *
 *     ->add(CalendarWriteRoleAuthMiddleware::class)   // runs second, sees `calendar`
 *     ->add(CalendarMiddleware::class)                // runs first, loads or 404s
 *
 * When the attribute is missing the middleware falls back to the global right alone, so a
 * mis-chained route fails closed rather than open.
 */
class CalendarWriteRoleAuthMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $user = AuthenticationManager::getCurrentUser();
        } catch (\Throwable $ex) {
            LoggerUtils::getAppLogger()->warning('User authentication failed in calendar role middleware', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'exception' => $ex->getMessage(),
            ]);

            return $this->refuse($request, gettext('No logged in user'), 401, 'Authentication');
        }

        $calendar = $request->getAttribute('calendar');

        if (!$this->mayWrite($user, $calendar instanceof Calendar ? $calendar : null)) {
            LoggerUtils::getAppLogger()->warning('User lacks write access to this calendar', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'user' => $user->getUserName(),
                'calendarId' => $calendar instanceof Calendar ? $calendar->getId() : null,
            ]);

            return $this->refuse(
                $request,
                gettext('User must have Add Event permission, or coordinate the volunteer ministry this calendar belongs to'),
                403,
                'AddEvent'
            );
        }

        return $handler->handle($request);
    }

    private function mayWrite(User $user, ?Calendar $calendar): bool
    {
        if ($user->canManageEvents()) {
            return true;
        }

        if ($calendar === null || !User::isVolunteerV2Enabled()) {
            return false;
        }

        $ministryId = $calendar->getMinistryId();
        if ($ministryId === null) {
            return false;
        }

        return (new VolunteerAuthorizationService())->canManageMinistry($user, (int) $ministryId);
    }

    private function refuse(Request $request, string $message, int $status, string $role): ResponseInterface
    {
        if ($this->isBrowserRequest($request)) {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', SystemURLs::getRootPath() . '/v2/access-denied?role=' . urlencode($role));
        }

        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message, 'code' => $status]));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
