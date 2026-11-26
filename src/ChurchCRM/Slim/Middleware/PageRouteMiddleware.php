<?php

namespace ChurchCRM\Slim\Middleware;

use FastRoute\RouteParser\Std;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

class PageRouteMiddleware implements MiddlewareInterface
{
    private static ?string $routePattern = null;

    public function __construct(private readonly string $basePath)
    {
    }

    public static function getRoutePattern(): ?string
    {
        return self::$routePattern;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = RouteContext::fromRequest($request);
        $route = $context->getRoute();
        $previous = self::$routePattern;
        // PHP templates render without the PSR request in their scope.
        self::$routePattern = null;
        if ($route !== null) {
            $variants = (new Std())->parse($route->getPattern());
            $pattern = implode('', array_map(
                static fn (string|array $segment): string => is_string($segment) ? $segment : '{' . $segment[0] . '}',
                end($variants),
            ));
            self::$routePattern = $this->basePath . $pattern;
        }

        try {
            return $handler->handle($request);
        } finally {
            self::$routePattern = $previous;
        }
    }
}
