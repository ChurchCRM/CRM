<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\FamilyQuery;
use Slim\Http\Request;
use Slim\Http\Response;

class FamilyAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {

        $familyId = $request->getAttribute("route")->getArgument("familyId");
        if (empty(trim($familyId))) {
          return $response->withStatus(412, gettext("Missing"). " FamilyId");
        }

        $family = FamilyQuery::create()->findPk($familyId);
        if (empty($family)) {
            return $response->withStatus(412, "FamilyId: " . $familyId . " ". gettext("not found"));
        }

        $request = $request->withAttribute("family", $family);
        return $next($request, $response);
    }

}
