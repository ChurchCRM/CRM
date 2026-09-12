<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerPool;
use ChurchCRM\model\ChurchCRM\VolunteerPoolQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9707): load a volunteer pool link and decide whether
 * the caller may touch THIS one.
 *
 * The per-record half of the three-layer model (design §4.5), in the shape the
 * #9706 middlewares established: AbstractEntityMiddleware resolves the route
 * argument and 404s when the row is missing; postEntityLoad() answers the scope
 * question and denies with 403.
 *
 * A pool's owner is polymorphic (§2.5), so the check follows `vpol_OwnerType`:
 * a ministry-owned pool is the ministry coordinator's, a team-owned pool the
 * team leader's (and the coordinator's above them, because canManageTeam()
 * unions the teams under every managed ministry, §4.4). A pool whose team row
 * has since gone is unreachable rather than open to everyone — canManageTeam()
 * on a missing id is false for anyone but a global manager.
 */
class VolunteerPoolMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'poolId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerPool';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerPoolQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Volunteer pool not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        $allowed = $entity->getOwnerType() === VolunteerPool::OWNER_TYPE_MINISTRY
            ? $authz->canManageMinistry($currentUser, (int) $entity->getOwnerId())
            : $authz->canManageTeam($currentUser, (int) $entity->getOwnerId());

        if (!$allowed) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this volunteer pool'), [], 403);
        }

        return null;
    }
}
