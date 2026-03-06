<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'userId';
    }

    protected function getAttributeName(): string
    {
        return 'user';
    }

    protected function loadEntity(string $id): mixed
    {
        return UserQuery::create()->findPk($id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('User not found');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $userId = SlimUtils::getRouteArgument($request, $this->getRouteParamName());

        if (empty(trim($userId))) {
            return SlimUtils::renderErrorJSON($response, gettext('Missing') . ' ' . $this->getRouteParamName(), [], 412);
        }

        $loggedInUser = AuthenticationManager::getCurrentUser();
        if ($loggedInUser->getId() == $userId) {
            return $handler->handle($request->withAttribute($this->getAttributeName(), $loggedInUser));
        }

        if (!$loggedInUser->isAdmin()) {
            return $response->withStatus(401);
        }

        return parent::process($request, $handler);
    }
}
