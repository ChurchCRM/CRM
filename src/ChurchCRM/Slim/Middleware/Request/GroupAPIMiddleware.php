<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class GroupAPIMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new Response();
        $groupId = SlimUtils::getRouteArgument($request, 'groupId');
        if (empty(trim($groupId))) {
            return $response->withStatus(412, gettext('Missing') . ' GroupId');
        }

        $group = GroupQuery::create()->findPk($groupId);
        if (empty($group)) {
            return $response->withStatus(412, 'GroupId: ' . $groupId . ' ' . gettext('not found'));
        }

        $request = $request->withAttribute('group', $group);

        return $handler->handle($request);
    }
}
