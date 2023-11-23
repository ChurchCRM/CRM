<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Laminas\Diactoros\Response;

class FamilyAPIMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
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
