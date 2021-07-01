<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\GroupQuery;
use Slim\Http\Request;
use Slim\Http\Response;

class GroupAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {

        $groupId = $request->getAttribute("route")->getArgument("groupId");
        if (empty(trim($groupId))) {
          return $response->withStatus(412, gettext("Missing"). " GroupId");
        }

        $group = GroupQuery::create()->findPk($groupId);
        if (empty($group)) {
            return $response->withStatus(412, "GroupId: " . $groupId . " ". gettext("not found"));
        }

        $request = $request->withAttribute("group", $group);
        return $next($request, $response);
    }

}
