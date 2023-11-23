<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EventsMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $eventId = $request->getAttribute('route')->getArgument('id');
        if (empty(trim($eventId))) {
            return SlimUtils::renderJSON($response, ['message' => gettext('Missing event id')], 400);
        }

        $event = EventQuery::Create()
          ->findPk($eventId);

        if (empty($event)) {
            return SlimUtils::renderJSON($response, ['message' => gettext('Event not found')], 404);
        }
        $request = $request->withAttribute('event', $event);

        return $next($request, $response);
    }
}
