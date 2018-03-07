<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemConfig;

class PublicCalendarAPIMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!SystemConfig::getBooleanValue("bEnableExternalCalendarAPI")) {
            return $response->withStatus(403, gettext("External Calendar API is disabled"));
        }
        return $next($request, $response);
    }
}
