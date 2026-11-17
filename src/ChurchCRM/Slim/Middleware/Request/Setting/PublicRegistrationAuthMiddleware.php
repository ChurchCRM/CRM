<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemConfig;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PublicRegistrationAuthMiddleware extends BaseAuthSettingMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Pages answer 404 when registration is off; the public API keeps the base 403.
        if (
            !SystemConfig::getBooleanValue($this->getSettingName())
            && !str_contains($request->getUri()->getPath(), '/api/')
        ) {
            return (new Response())->withStatus(404);
        }

        return parent::process($request, $handler);
    }

    protected function getSettingName(): string
    {
        return 'bEnableSelfRegistration';
    }
}
