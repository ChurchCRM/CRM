<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemConfig;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class BaseAuthSettingMiddleware
{

    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!SystemConfig::getBooleanValue($this->getSettingName())) {
            return $response->withStatus(403, $this->getSettingName() . " " . gettext("is disabled"));
        }

        return $next($request, $response);
    }

    abstract function getSettingName();
}
