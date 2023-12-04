<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class PersonAPIMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new Response();
        $personId = SlimUtils::getRouteArgument($request, 'personId');
        if (empty(trim($personId))) {
            return $response->withStatus(412, gettext('Missing') . ' PersonId');
        }

        $person = PersonQuery::create()->findPk($personId);
        if (empty($person)) {
            return $response->withStatus(412, 'PersonId : ' . $personId . ' ' . gettext('not found'));
        }

        $request = $request->withAttribute('person', $person);

        return $handler->handle($request);
    }
}
