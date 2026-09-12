<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9706): load a volunteer team and decide whether the caller may
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
 *
 * A ministry coordinator passes here without a team scope row: canManageTeam()
 * unions the teams under every managed ministry (§4.4).
 */
class VolunteerTeamMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'teamId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerTeam';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerTeamQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Team not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManageTeam($currentUser, (int) $entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this team'), [], 403);
        }

        return null;
    }
}
