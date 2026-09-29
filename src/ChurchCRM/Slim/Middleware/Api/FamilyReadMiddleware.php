<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Low-sensitivity family read middleware.
 *
 * Intended for non-sensitive GET endpoints (avatar, nav, photo GET) that should
 * be accessible to any API-authenticated user, regardless of whether they are
 * scoped to their own family via the EditSelf+Notes permission combination.
 *
 * This middleware:
 *   - Loads the family entity and returns 404 if it does not exist (same as FamilyMiddleware).
 *   - Uses User::canReadFamily() for authorisation (currently always true — the
 *     entry gate is User::isEditSelfExclusive() (checked by AuthMiddleware), NOT this middleware).
 *   - Does NOT enforce the family-scope restriction from GHSA-jjcj-h3cm-p7x7.
 *     That restriction belongs on FamilyMiddleware (sensitive/write endpoints only).
 *
 * @see FamilyMiddleware for the scope-enforcing version used on sensitive endpoints.
 */
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

    /**
     * Apply read-baseline authorisation only.
     *
     * Unlike FamilyMiddleware, this does NOT call canViewFamily() and therefore
     * never restricts EditSelf-scoped users to their own family. Access is still
     * gated by AuthMiddleware upstream (User::isEditSelfExclusive()).
     *
     * The canReadFamily() call exists for defence-in-depth and as a hook point
     * for future row-level security (e.g. pastoral-confidentiality holds). It
     * currently always returns true.
     */
    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canReadFamily($entity->getId())) {
            return SlimUtils::renderErrorJSON(new Response(), gettext('Access denied'), [], 403);
        }
        return null;
    }
}
