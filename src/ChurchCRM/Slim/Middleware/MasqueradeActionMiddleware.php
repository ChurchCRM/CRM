<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\ImpersonationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records every write request made during a Login as User session (#9843) in
 * `user_masquerade_action_uma`, with the response status.
 *
 * Added app-wide right after AuthMiddleware in every Slim entry point that
 * authenticates (MvcAppFactory, api/index.php, plugins/index.php); legacy pages
 * get the same through ImpersonationService::recordLegacyRequest() in PageInit.php.
 */
class MasqueradeActionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $sessionId = ImpersonationService::getRecordingSessionId($method, $path);
        if ($sessionId === null) {
            return $handler->handle($request);
        }

        // A handler that ends with RedirectUtils::redirect() exits before it returns.
        $recorded = false;
        register_shutdown_function(static function () use (&$recorded, $sessionId, $method, $path): void {
            if (!$recorded) {
                $status = http_response_code();
                ImpersonationService::recordAction($sessionId, $method, $path, is_int($status) ? $status : null);
            }
        });

        $response = $handler->handle($request);
        $recorded = true;
        ImpersonationService::recordAction($sessionId, $method, $path, $response->getStatusCode());

        return $response;
    }
}
