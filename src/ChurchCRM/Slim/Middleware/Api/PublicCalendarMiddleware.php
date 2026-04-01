<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\dto\SystemConfig;
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

class PublicCalendarMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        if (!SystemConfig::getBooleanValue('bEnableExternalCalendarAPI')) {
            return $response->withStatus(403, gettext('External Calendar API is disabled'));
        }

        $CAT = SlimUtils::getRouteArgument($request, 'CalendarAccessToken');
        if (empty(trim($CAT))) {
            return SlimUtils::renderJSON($response, ['message' => gettext('Missing calendar access token')], 400);
        }

        $calendar = CalendarQuery::create()
            ->filterByAccessToken($CAT)
            ->findOne();
        if (empty($calendar)) {
            return SlimUtils::renderJSON($response, ['message' => gettext('Calendar access token not found')], 404);
        }

        $request = $request->withAttribute('calendar', $calendar);
        $events = $this->getEvents($request, $calendar);
        if ($events === null) {
            return SlimUtils::renderJSON($response, ['message' => gettext('Invalid date format in start parameter')], 400);
        }
        $request = $request->withAttribute('events', $events);

        return $handler->handle($request);
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
