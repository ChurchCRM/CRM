<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use DateTime;
use Propel\Runtime\ActiveQuery\Criteria;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class PublicCalendarAPIMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
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
        $request = $request->withAttribute('events', $events);

        return $handler->handle($request);
    }

    private function getEvents(Request $request, Calendar $calendar)
    {
        $params = $request->getQueryParams();
        if (isset($params['start'])) {
            $start_date = DateTime::createFromFormat('Y-m-d', $params['start']);
        } else {
            $start_date = new DateTime();
        }
        $start_date->setTime(0, 0, 0);

        $events = EventQuery::create()
            ->joinCalendarEvent()
            ->useCalendarEventQuery()
            ->filterByCalendar($calendar)
            ->endUse()
            ->orderBy(EventTableMap::COL_EVENT_START);

        if ($start_date) {
            $events->filterByStart($start_date, Criteria::GREATER_EQUAL);
        }

        if (array_key_exists('max', $params)) {
            $max_events = InputUtils::filterInt($params['max']);
            $events->limit($max_events);
        }

        return $events->find();
    }
}
