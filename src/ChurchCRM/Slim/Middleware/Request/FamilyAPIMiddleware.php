<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FamilyAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $familyId = $request->getAttribute('route')->getArgument('familyId');
        if (empty(trim($familyId))) {
            return $response->withStatus(412, gettext('Missing').' FamilyId');
        }

        $family = FamilyQuery::create()->findPk($familyId);
        if (empty($family)) {
            return $response->withStatus(412, 'FamilyId: '.$familyId.' '.gettext('not found'));
        }

        $request = $request->withAttribute('family', $family);

        return $next($request, $response);
    }
}
