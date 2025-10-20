<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class VersionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withAddedHeader('CRM_VERSION', VersionUtils::getInstalledVersion());
    }
}
