<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\PersonQuery;

class PersonAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {

        $personId = $request->getAttribute("route")->getArgument("personId");
        if (empty(trim($personId))) {
          return $response->withStatus(400)->withJson(["message" => gettext("Missing PersonId")]);
        }

        $person = PersonQuery::create()->findPk($personId);
        if (empty($person)) {
            return $response->withStatus(404, gettext('The person could not be found.'));
        }

        $request = $request->withAttribute("person", $person);
        return $next($request, $response);
    }

}
