<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FamilyMiddleware extends AbstractEntityMiddleware
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

    /**
     * Enforce the family-scope restriction for EditSelf-only users.
     *
     * Fixes GHSA-jjcj-h3cm-p7x7: EditSelf users were able to access any
     * family's data. Now restricted to their own family only.
     *
     * Admin and EditRecords users pass through unrestricted (canViewFamily()
     * returns true for them).
     */
    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canViewFamily($entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Access denied'), [], 403);
        }
        return null;
    }
}
