<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class VersionMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        return $response->withAddedHeader('CRM_VERSION', SystemService::getInstalledVersion());
    }
}
