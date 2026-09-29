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

    /**
     * Optional authorisation hook called after the entity has been loaded.
     *
     * Override this method instead of overriding process() when the only
     * customisation needed is an extra permission check after the entity exists.
     * Return a ResponseInterface to short-circuit the chain (deny access), or
     * null to continue to the route handler.
     *
     * This ensures that any future cross-cutting change to process() — audit
     * logging, rate-limiting, stricter guards — automatically applies to all
     * subclasses without requiring them each to be updated.
     */
    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        return null;
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

        $authResponse = $this->postEntityLoad($request, $entity);
        if ($authResponse !== null) {
            return $authResponse;
        }

        return $handler->handle($request->withAttribute($this->getAttributeName(), $entity));
    }
}
