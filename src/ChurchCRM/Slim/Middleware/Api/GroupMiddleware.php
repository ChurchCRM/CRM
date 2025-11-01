<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Slim\SlimUtils;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;

class GroupMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
