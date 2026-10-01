<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\Slim\Middleware\Api\AbstractEntityMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9707): load a qualification row and decide whether
 * the caller may touch THIS one.
 *
 * The per-record half of the three-layer model (design §4.5), in the shape the
 * #9706 middlewares established.
 *
 * A qualification belongs to its POSITION, not to the person it names, so the
 * decision is `canManagePosition()` — which already says that a position belongs
 * to its team, and so to that team's leader and the ministry coordinator above
 * them (§4.6). Routing it through that one predicate is what keeps "a team leader
 * may grant for their own team's positions" from being written twice.
 */
class VolunteerQualificationMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'qualificationId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerQualification';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerQualificationQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Qualification not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManagePosition($currentUser, (int) $entity->getPositionId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this qualification'), [], 403);
        }

        return null;
    }
}
