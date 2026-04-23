<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use DateTime;
use Propel\Runtime\ActiveQuery\Criteria;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\PhpRenderer;

class PublicCalendarMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();

        if (!SystemConfig::getBooleanValue('bEnableExternalCalendarAPI')) {
            return $this->renderError(
                $request,
                $response,
                403,
                gettext('External calendar sharing is disabled'),
                gettext('The church administrator has not enabled external calendar sharing. Please contact them if you believe this is in error.'),
                'ti-lock',
            );
        }

        $CAT = SlimUtils::getRouteArgument($request, 'CalendarAccessToken');
        if (empty(trim($CAT))) {
            return $this->renderError(
                $request,
                $response,
                400,
                gettext('Missing calendar access token'),
                gettext('The calendar link is incomplete. Please check the URL with the person who sent it to you.'),
                'ti-link-off',
            );
        }

        $calendar = CalendarQuery::create()
            ->filterByAccessToken($CAT)
            ->findOne();
        if (empty($calendar)) {
            return $this->renderError(
                $request,
                $response,
                404,
                gettext('Calendar not found'),
                gettext('This calendar link is invalid or has been revoked. Ask the church for a current link.'),
                'ti-calendar-off',
            );
        }

        $request = $request->withAttribute('calendar', $calendar);
        $events = $this->getEvents($request, $calendar);
        if ($events === null) {
            return $this->renderError(
                $request,
                $response,
                400,
                gettext('Invalid date format'),
                gettext('The start or end date in the link could not be understood. Try the base calendar link without date parameters.'),
                'ti-calendar-question',
            );
        }
        $request = $request->withAttribute('events', $events);

        return $handler->handle($request);
    }

    /**
     * Render an error for the public calendar flow. JSON routes
     * (`/api/public/calendar/...`) get a JSON body; the browser-facing
     * HTML route (`/external/calendars/...`) gets a friendly HTML page
     * rendered with the unauthenticated header so the user sees the
     * church branding and a plain explanation instead of a raw status code.
     */
    private function renderError(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $status,
        string $title,
        string $message,
        string $icon = 'ti-calendar-off',
    ): ResponseInterface {
        if ($this->prefersJson($request)) {
            return SlimUtils::renderJSON(
                $response,
                ['error' => $title, 'message' => $message],
                $status,
            );
        }

        $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/external/templates/calendar/');

        return $renderer->render(
            $response->withStatus($status),
            'error.php',
            ['title' => $title, 'message' => $message, 'icon' => $icon],
        );
    }

    /**
     * JSON for the `/api/public/calendar/...` endpoints (machine clients
     * and the embedded FullCalendar fetch), HTML for everything else.
     * Falls back to JSON if the client explicitly asks for it via Accept.
     */
    private function prefersJson(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        if (str_contains($path, '/api/')) {
            return true;
        }
        $accept = strtolower($request->getHeaderLine('Accept'));
        if ($accept !== '' && str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    private function getEvents(ServerRequestInterface $request, Calendar $calendar): mixed
    {
        $params = $request->getQueryParams();

        // Parse start param — accepts both Y-m-d (plain JSON endpoint) and ISO 8601 (FullCalendar)
        $start_date = null;
        if (isset($params['start'])) {
            $start_date = DateTime::createFromFormat(DateTime::ATOM, $params['start'], DateTimeUtils::getConfiguredTimezone())
                ?? DateTime::createFromFormat('Y-m-d\TH:i:s', $params['start'], DateTimeUtils::getConfiguredTimezone())
                ?? DateTime::createFromFormat('Y-m-d', $params['start'], DateTimeUtils::getConfiguredTimezone());
            if ($start_date === false || $start_date === null) {
                return null;
            }
            $start_date->setTime(0, 0, 0);
        }

        // Parse end param — accepts both Y-m-d and ISO 8601 (FullCalendar sends ISO)
        $end_date = null;
        if (isset($params['end'])) {
            $end_date = DateTime::createFromFormat(DateTime::ATOM, $params['end'], DateTimeUtils::getConfiguredTimezone())
                ?? DateTime::createFromFormat('Y-m-d\TH:i:s', $params['end'], DateTimeUtils::getConfiguredTimezone())
                ?? DateTime::createFromFormat('Y-m-d', $params['end'], DateTimeUtils::getConfiguredTimezone());
            if ($end_date === false || $end_date === null) {
                return null;
            }
        }

        $events = EventQuery::create()
            ->joinCalendarEvent()
            ->useCalendarEventQuery()
            ->filterByCalendar($calendar)
            ->endUse()
            ->orderBy(EventTableMap::COL_EVENT_START);

        if ($start_date !== null) {
            $events->filterByStart($start_date, Criteria::GREATER_EQUAL);
        }

        if ($end_date !== null) {
            $events->filterByEnd($end_date, Criteria::LESS_EQUAL);
        }

        if (array_key_exists('max', $params)) {
            $max_events = InputUtils::filterInt($params['max']);
            $events->limit($max_events);
        }

        return $events->find();
    }
}
