<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class UserAPIMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
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
