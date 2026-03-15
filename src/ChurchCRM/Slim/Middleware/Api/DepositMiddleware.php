<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\DepositQuery;

class DepositMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'id';
    }

    protected function getAttributeName(): string
    {
        return 'deposit';
    }

    protected function loadEntity(string $id): mixed
    {
        return DepositQuery::create()->findOneById($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Deposit not found');
    }
}
