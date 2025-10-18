<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;

class PersonMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
