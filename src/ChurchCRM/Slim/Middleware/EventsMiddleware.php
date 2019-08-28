<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Calendar;
use DateTime;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\EventQuery;
use ChurchCRM\Map\EventTableMap;

class EventsMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {      
        $eventId = $request->getAttribute("route")->getArgument("id");
        if (empty(trim($eventId))) {
          return $response->withStatus(400)->withJson(["message" => gettext("Missing event id")]);
        }
        
        $event = EventQuery::Create()
          ->findPk($eventId);

        if (empty($event)) {
          return $response->withStatus(404)->withJson(["message" => gettext("Event not found")]);
        }
        $request = $request->withAttribute("event", $event);
        return $next($request, $response);
    }
}
