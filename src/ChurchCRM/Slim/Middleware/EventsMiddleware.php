<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Api\AbstractEntityMiddleware;

class EventsMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'id';
    }

    protected function getAttributeName(): string
    {
        return 'event';
    }

    protected function loadEntity(string $id): mixed
    {
        return EventQuery::create()->findPk($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Event not found');
    }
}
