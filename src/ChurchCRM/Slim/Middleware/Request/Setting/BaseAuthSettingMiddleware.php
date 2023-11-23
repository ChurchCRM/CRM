<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemConfig;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

abstract class BaseAuthSettingMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (!SystemConfig::getBooleanValue($this->getSettingName())) {
            $response = new Response();
            return $response->withStatus(403, $this->getSettingName() . ' ' . gettext('is disabled'));
        }

        return $handler->handle($request);
    }

    abstract protected function getSettingName();
}
