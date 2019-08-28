<?php

namespace ChurchCRM\Slim\Middleware\Request;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\CalendarQuery;
use ChurchCRM\Calendar;
use DateTime;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\EventQuery;
use ChurchCRM\Map\EventTableMap;

class PublicCalendarAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!SystemConfig::getBooleanValue("bEnableExternalCalendarAPI")) {
            return $response->withStatus(403, gettext("External Calendar API is disabled"));
        }
        
        $CAT = $request->getAttribute("route")->getArgument("CalendarAccessToken");
        if (empty(trim($CAT))) {
          return $response->withStatus(400)->withJson(["message" => gettext("Missing calendar access token")]);
        }
        
        $calendar = CalendarQuery::create()
            ->filterByAccessToken($CAT)
            ->findOne();
        if (empty($calendar)) {
          return $response->withStatus(404)->withJson(["message" => gettext("Calendar access token not found")]);
        }

        $request = $request->withAttribute("calendar", $calendar);
        $events = $this->getEvents($request, $calendar);
        $request = $request->withAttribute("events", $events);
        return $next($request, $response);
    }
    
    private function getEvents(Request $request, Calendar $calendar)
    {
        $params = $request->getQueryParams();
        if (isset($params['start'])) {
            $start_date = DateTime::createFromFormat("Y-m-d", $params['start']);
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

        if(array_key_exists('max',$params)) {
            $max_events = InputUtils::FilterInt($params['max']);
            $events->limit($max_events);
        }

        return $events->find();
    }
}
