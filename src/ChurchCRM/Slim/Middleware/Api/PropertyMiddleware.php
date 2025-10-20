<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;

class PropertyMiddleware implements MiddlewareInterface
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
