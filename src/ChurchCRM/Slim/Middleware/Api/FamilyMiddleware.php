<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\SlimUtils;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response;

class FamilyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $familyId = SlimUtils::getRouteArgument($request, 'familyId');
        if (empty(trim($familyId))) {
            return $response->withStatus(412, gettext('Missing') . ' FamilyId');
        }

        $family = FamilyQuery::create()->findPk($familyId);
        if (empty($family)) {
            return $response->withStatus(412, 'FamilyId: ' . $familyId . ' ' . gettext('not found'));
        }

        $request = $request->withAttribute('family', $family);

        return $handler->handle($request);
    }
}
