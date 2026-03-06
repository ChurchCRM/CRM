<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\PersonQuery;

class PersonMiddleware extends AbstractEntityMiddleware
{
    public function __construct(private readonly string $routeParamName = 'personId') {}

    protected function getRouteParamName(): string
    {
        return $this->routeParamName;
    }

    protected function getAttributeName(): string
    {
        return 'person';
    }

    protected function loadEntity(string $id): mixed
    {
        return PersonQuery::create()->findPk($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Person not found');
    }
}
