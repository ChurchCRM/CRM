<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class VersionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)->withHeader('X-Content-Type-Options', 'nosniff');

        if (!$response->hasHeader('Cache-Control') && !str_contains($request->getUri()->getPath(), '/api/public')) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        if (AuthenticationManager::isUserAuthenticated()) {
            $response = $response->withAddedHeader('X-CRM-Version', VersionUtils::getInstalledVersion());
        }

        return $response;
    }
}
