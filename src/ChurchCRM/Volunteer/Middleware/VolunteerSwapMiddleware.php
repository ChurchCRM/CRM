<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerSwapQuery;
use ChurchCRM\Slim\Middleware\Api\AbstractEntityMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9706): load a volunteer substitution request and decide whether the caller may
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
class VolunteerSwapMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'swapId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerSwap';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerSwapQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Substitution request not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManageSwap($currentUser, $entity)) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this substitution request'), [], 403);
        }

        return null;
    }
}
