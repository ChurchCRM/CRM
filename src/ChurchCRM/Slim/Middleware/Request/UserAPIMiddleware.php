<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;

class UserAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {

        $userId = $request->getAttribute("route")->getArgument("userId");
        if (empty(trim($userId))) {
          return $response->withStatus(412, gettext("Missing"). " UserId");
        }

        $loggedInUser = AuthenticationManager::GetCurrentUser();
        if ($loggedInUser->getId() == $userId) {
            $user = $loggedInUser;
        } elseif ($loggedInUser->isAdmin()) {
            $user = UserQuery::create()->findPk($userId);
            if (empty($user)) {
                return $response->withStatus(412, "User : " . $userId . " ". gettext("not found"));
            }
        } else {
            return $response->withStatus(401);
        }

        $request = $request->withAttribute("user", $user);
        return $next($request, $response);
    }

}
