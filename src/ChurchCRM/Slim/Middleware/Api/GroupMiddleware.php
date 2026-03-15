<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\GroupQuery;

class GroupMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'groupID';
    }

    protected function getAttributeName(): string
    {
        return 'group';
    }

    protected function loadEntity(string $id): mixed
    {
        return GroupQuery::create()->findPk($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Group not found');
    }
}
