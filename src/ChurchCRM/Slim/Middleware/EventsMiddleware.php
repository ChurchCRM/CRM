<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class EventsMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $eventId = SlimUtils::getRouteArgument($request, 'id');
        if (empty(trim($eventId))) {
            $response = new Response();
            return SlimUtils::renderJSON($response, ['message' => gettext('Missing event id')], 400);
        }

        $event = EventQuery::Create()->findPk($eventId);

        if (empty($event)) {
            $response = new Response();
            return SlimUtils::renderJSON($response, ['message' => gettext('Event not found')], 404);
        }
        $request = $request->withAttribute('event', $event);

        return $handler->handle($request);
    }
}
