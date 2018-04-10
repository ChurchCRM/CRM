<?php

namespace ChurchCRM\Slim\Middleware\Request;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\PersonQuery;

class PersonAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {

        $personId = $request->getAttribute("route")->getArgument("personId");
        if (empty(trim($personId))) {
          return $response->withStatus(400, gettext("Missing"). " PersonId");
        }

        $person = PersonQuery::create()->findPk($personId);
        if (empty($person)) {
            return $response->withStatus(404, "PersonId : " . $personId . " ". gettext("not found"));
        }

        $request = $request->withAttribute("person", $person);
        return $next($request, $response);
    }

}
