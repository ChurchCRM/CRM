<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Volunteer Management v2 (#9706): load a volunteer assignment and decide whether the caller may
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
 * COORDINATOR authority only. The volunteer's own "this is my assignment" right is a
 * different question (canRespondToAssignment()) on a different surface — /api/ministries/me
 * — which derives the person from the session and takes no personId parameter (§3.3.3), so
 * this middleware is deliberately not reused there.
 */
class VolunteerAssignmentMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'assignmentId';
    }

    protected function getAttributeName(): string
    {
        return 'volunteerAssignment';
    }

    protected function loadEntity(string $id): mixed
    {
        return VolunteerAssignmentQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Assignment not found');
    }

    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManageAssignment($currentUser, $entity)) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this assignment'), [], 403);
        }

        return null;
    }
}
