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
     * Fixes GHSA-jjcj-h3cm-p7x7: EditSelf users were able to access any
     * family's data. Now restricted to their own family only.
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

        // Authorization: enforce family-scope for EditSelf-only users.
        // Admin and EditRecords users pass through unrestricted.
        $currentUser = AuthenticationManager::getCurrentUser();
        if (!$currentUser->canViewFamily((int) $id)) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        return $handler->handle($request->withAttribute($this->getAttributeName(), $entity));
    }
}
