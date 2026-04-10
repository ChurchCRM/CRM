<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\CalendarQuery;

class CalendarMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'id';
    }

    protected function getAttributeName(): string
    {
        return 'calendar';
    }

    protected function loadEntity(string $id): mixed
    {
        return CalendarQuery::create()->findOneById($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Calendar not found');
    }
}
