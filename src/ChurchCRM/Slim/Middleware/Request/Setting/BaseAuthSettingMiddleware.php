<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemConfig;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseAuthSettingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!SystemConfig::getBooleanValue($this->getSettingName())) {
            $response = new Response();
            return $response->withStatus(403, $this->getSettingName() . ' ' . gettext('is disabled'));
        }
        return $handler->handle($request);
    }

    abstract protected function getSettingName();
}
