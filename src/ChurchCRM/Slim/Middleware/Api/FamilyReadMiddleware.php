<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FamilyReadMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'familyId';
    }

    protected function getAttributeName(): string
    {
        return 'family';
    }

    protected function loadEntity(string $id): mixed
    {
        return FamilyQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Family not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canReadFamily($entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Access denied'), [], 403);
        }
        return null;
    }
}
