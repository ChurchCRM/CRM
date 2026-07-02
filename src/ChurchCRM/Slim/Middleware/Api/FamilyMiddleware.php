<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FamilyMiddleware extends AbstractEntityMiddleware
{
    /**
     * When true (the default) this middleware enforces the family-scope restriction
     * for EditSelf-scoped users via User::canViewFamily() — as required by the
     * GHSA-jjcj-h3cm-p7x7 security fix.
     *
     * Pass false for low-sensitivity endpoints (avatar, nav, photo GET). In this mode
     * FamilyMiddleware only calls User::canReadFamily() (currently always true) and does
     * not itself enforce any additional access gate. The entry-level permission check is
     * handled upstream by AuthMiddleware::hasNoAdminPermissions(). Entity loading and
     * 404-on-missing-family are preserved; only the view-scope restriction is relaxed.
     */
    public function __construct(private bool $enforceViewScope = true)
    {
    }

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
     * Override process() to add object-level authorization check after loading
     * the entity but before invoking the route handler.
     *
     * When $enforceViewScope is true (default):
     *   Uses canViewFamily() — EditSelf-only users are restricted to their own family.
     *   Fixes GHSA-jjcj-h3cm-p7x7: EditSelf users were able to access any family's
     *   data. Now restricted to their own family only.
     *
     * When $enforceViewScope is false (read-baseline mode):
     *   Uses canReadFamily() (currently always true). Access is gated upstream by
     *   AuthMiddleware::hasNoAdminPermissions(); this middleware does not add an
     *   additional permission requirement beyond that entry gate.
     *   Entity existence is still validated (404 if family not found above).
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $id = SlimUtils::getRouteArgument($request, $this->getRouteParamName());

        if (empty(trim($id))) {
            return SlimUtils::renderErrorJSON($response, gettext('Missing') . ' ' . $this->getRouteParamName(), [], 412);
        }

        $entity = $this->loadEntity($id);

        if (empty($entity)) {
            return SlimUtils::renderErrorJSON($response, $this->getNotFoundMessage(), [], 404);
        }

        $currentUser = AuthenticationManager::getCurrentUser();

        if ($this->enforceViewScope) {
            // Sensitive endpoints: enforce family-scope for EditSelf-only users.
            // Admin and EditRecords users pass through unrestricted.
            if (!$currentUser->canViewFamily((int) $id)) {
                return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
            }
        } else {
            // Low-sensitivity endpoints: use the read-default baseline.
            // canReadFamily() returns true for all authenticated users; this branch
            // exists for defence-in-depth and future row-level security hooks.
            if (!$currentUser->canReadFamily((int) $id)) {
                return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
            }
        }

        return $handler->handle($request->withAttribute($this->getAttributeName(), $entity));
    }
}
