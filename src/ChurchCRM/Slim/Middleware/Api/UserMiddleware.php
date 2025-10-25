<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\SlimUtils;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;

class UserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $userId = SlimUtils::getRouteArgument($request, 'userId');
        if (empty(trim($userId))) {
            return $response->withStatus(412, gettext('Missing') . ' UserId');
        }

        $loggedInUser = AuthenticationManager::getCurrentUser();
        if ($loggedInUser->getId() == $userId) {
            $user = $loggedInUser;
        } elseif ($loggedInUser->isAdmin()) {
            $user = UserQuery::create()->findPk($userId);
            if (empty($user)) {
                return $response->withStatus(412, 'User : ' . $userId . ' ' . gettext('not found'));
            }
        } else {
            return $response->withStatus(401);
        }

        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }
}
