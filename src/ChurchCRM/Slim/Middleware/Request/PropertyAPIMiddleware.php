<?php

namespace ChurchCRM\Slim\Middleware\Request;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\PropertyQuery;
use ChurchCRM\Utils\LoggerUtils;

class PropertyAPIMiddleware
{
    protected $type;

    public function __construct(String $type)
    {
        $this->type = $type;
    }

    public function __invoke(Request $request, Response $response, callable $next)
    {

        $propertyId = $request->getAttribute("route")->getArgument("propertyId");

        if (empty(trim($propertyId))) {
          return $response->withStatus(412, gettext("Missing"). " PropertyId");
        }

        $property = PropertyQuery::create()->findPk($propertyId);

        LoggerUtils::getAppLogger()->debug("Pro Type is " . $property->getPropertyType()->getPrtClass() . " Looking for " . $this->type);

        if (empty($property)) {
            return $response->withStatus(412, "PropertyId : " . $propertyId . " ". gettext("not found"));
        } else if ($property->getPropertyType()->getPrtClass() != $this->type) {
            return $response->withStatus(500, "PropertyId : " . $propertyId . " ". gettext(" has a type mismatch"));
        }

        $request = $request->withAttribute("property", $property);
        return $next($request, $response);
    }

}
