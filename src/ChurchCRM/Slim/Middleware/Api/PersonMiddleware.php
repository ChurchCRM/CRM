<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
        return PersonQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Person not found');
    }

    /**
     * Members of a protected household answer 404, the same as a missing person,
     * so the response does not disclose that the record exists.
     */
    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        if (!AuthenticationManager::getCurrentUser()->canReadPerson($entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), $this->getNotFoundMessage(), [], 404);
        }

        return null;
    }
}
