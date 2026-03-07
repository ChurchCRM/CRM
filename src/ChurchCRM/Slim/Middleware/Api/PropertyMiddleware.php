<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\PropertyQuery;

class PropertyMiddleware extends AbstractEntityMiddleware
{
    public function __construct(private readonly string $type) {}

    protected function getRouteParamName(): string
    {
        return 'propertyId';
    }

    protected function getAttributeName(): string
    {
        return 'property';
    }

    protected function loadEntity(string $id): mixed
    {
        $property = PropertyQuery::create()->findPk($id);
        if ($property !== null && $property->getPropertyType()->getPrtClass() !== $this->type) {
            return null;
        }

        return $property;
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Property not found');
    }
}
