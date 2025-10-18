<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class EventsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $eventId = SlimUtils::getRouteArgument($request, 'id');
        if (empty(trim($eventId))) {
            $response = new Response();
            return SlimUtils::renderJSON($response, ['message' => gettext('Missing event id')], 400);
        }

        $event = EventQuery::create()->findPk($eventId);

        if (empty($event)) {
            $response = new Response();
            return SlimUtils::renderJSON($response, ['message' => gettext('Event not found')], 404);
        }
        $request = $request->withAttribute('event', $event);

        return $handler->handle($request);
    }
}
