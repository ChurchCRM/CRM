<?php

declare(strict_types=1);

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        return PropertyQuery::create()->findPk($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Property not found');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $propertyId = SlimUtils::getRouteArgument($request, $this->getRouteParamName());

        if (empty(trim($propertyId))) {
            return SlimUtils::renderErrorJSON($response, gettext('Missing') . ' ' . $this->getRouteParamName(), [], 412);
        }

        $property = $this->loadEntity($propertyId);

        if (empty($property)) {
            return SlimUtils::renderErrorJSON($response, $this->getNotFoundMessage(), [], 404);
        }

        if ($property->getPropertyType()->getPrtClass() !== $this->type) {
            return SlimUtils::renderErrorJSON($response, 'PropertyId : ' . $propertyId . gettext(' has a type mismatch'), [], 500);
        }

        return $handler->handle($request->withAttribute($this->getAttributeName(), $property));
    }
}
