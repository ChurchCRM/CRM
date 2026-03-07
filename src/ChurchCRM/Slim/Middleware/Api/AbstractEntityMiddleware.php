<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractEntityMiddleware implements MiddlewareInterface
{
    abstract protected function getRouteParamName(): string;

    abstract protected function getAttributeName(): string;

    abstract protected function loadEntity(string $id): mixed;

    protected function getNotFoundMessage(): string
    {
        return ucfirst($this->getAttributeName()) . ' ' . gettext('not found');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $id = SlimUtils::getRouteArgument($request, $this->getRouteParamName());

        if (empty(trim($id))) {
            return SlimUtils::renderErrorJSON($response, gettext('Missing') . ' ' . $this->getRouteParamName(), [], 412);
        }

        $entity = $this->loadEntity($id);

        if (empty($entity)) {
            return SlimUtils::renderErrorJSON($response, $this->getNotFoundMessage(), [], 404);
        }

        return $handler->handle($request->withAttribute($this->getAttributeName(), $entity));
    }
}
