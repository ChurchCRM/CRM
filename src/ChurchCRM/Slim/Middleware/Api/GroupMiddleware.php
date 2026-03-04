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
        $groupId = SlimUtils::getRouteArgument($request, 'groupID');
        if (empty(trim($groupId))) {
            return SlimUtils::renderErrorJSON($response, gettext('Missing') . ' GroupId', [], 412);
        }

        $group = GroupQuery::create()->findPk($groupId);
        if (empty($group)) {
            return SlimUtils::renderErrorJSON($response, 'GroupId: ' . $groupId . ' ' . gettext('not found'), [], 412);
        }

        $request = $request->withAttribute('group', $group);

        return $handler->handle($request);
    }
}
