<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;

class KioskDeviceMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'kioskId';
    }

    protected function getAttributeName(): string
    {
        return 'kioskDevice';
    }

    protected function loadEntity(string $id): mixed
    {
        return KioskDeviceQuery::create()->findOneById($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Kiosk not found');
    }
}
