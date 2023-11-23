<?php

namespace ChurchCRM\Slim\Middleware\Request;

use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class PropertyAPIMiddleware
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $propertyId = SlimUtils::getRouteArgument($request, 'propertyId');
        $response = new Response();
        if (empty(trim($propertyId))) {
            return $response->withStatus(412, gettext('Missing') . ' PropertyId');
        }

        $property = PropertyQuery::create()->findPk($propertyId);

        if (empty($property)) {
            LoggerUtils::getAppLogger()->debug('Pro Type is ' . $property->getPropertyType()->getPrtClass() . ' Looking for ' . $this->type);

            return $response->withStatus(412, 'PropertyId : ' . $propertyId . ' ' . gettext('not found'));
        } elseif ($property->getPropertyType()->getPrtClass() != $this->type) {
            return $response->withStatus(500, 'PropertyId : ' . $propertyId . ' ' . gettext(' has a type mismatch'));
        }

        $request = $request->withAttribute('property', $property);

        return $handler->handle($request);
    }
}
