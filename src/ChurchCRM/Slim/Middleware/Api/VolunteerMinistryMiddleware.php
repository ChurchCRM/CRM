<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9706): load a volunteer ministry and decide whether the caller may
 * touch THIS one.
 *
 * The per-record half of the three-layer model (design §4.5): the role middleware has
 * already established that the caller coordinates something; AbstractEntityMiddleware
 * resolves the route argument into the row and 404s when it is missing; postEntityLoad()
 * — the documented, previously single-use hook (F25) whose shape is FamilyMiddleware:43-50
 * — answers the scope question and denies with 403.
 *
 * 404 before 403 is deliberate and matches every other entity middleware: a caller who
 * cannot see a ministry learns nothing from "not found" either way.
 */
class VolunteerMinistryMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'ministryId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerMinistry';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerMinistryQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Ministry not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManageMinistry($currentUser, (int) $entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this ministry'), [], 403);
        }

        return null;
    }
}
