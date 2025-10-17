<?php

namespace ChurchCRM\Slim;

use ChurchCRM\dto\Photo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;
use Slim\HttpCache\CacheProvider;

class SlimUtils
{
    /**
     * Registers custom error, not found, and not allowed handlers on the Slim container
     */
    public static function registerCustomErrorHandlers($container)
    {
        // Error handler: returns JSON with error details
        $container->set('errorHandler', fn ($container): \Closure => function ($request, $response, $exception) use ($container) {
            $data = [
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => explode("\n", $exception->getTraceAsString()),
            ];
            return $container->get('response')->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($data, JSON_THROW_ON_ERROR));
        });

        // Not found handler: returns HTML 404
        $container->set('notFoundHandler', fn ($container): \Closure => fn ($request, $response) => $container['response']
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write("Can't find route for " . $request->getMethod() . ' on ' . $request->getUri()));

        // Not allowed handler: returns HTML 405
        $container->set('notAllowedHandler', fn ($container): \Closure => fn ($request, $response, $methods) => $container['response']
            ->withStatus(405)
            ->withHeader('Allow', implode(', ', $methods))
            ->withHeader('Content-type', 'text/html')
            ->write('Method must be one of: ' . implode(', ', $methods)));
    }
    /**
     * Setup Monolog error handler for Slim error middleware
     */
    public static function setupErrorLogger($errorMiddleware, $logPath = null)
    {
        if ($logPath === null) {
            $logPath = __DIR__ . '/../../../logs/slim-error.log';
        }
        $logger = new \Monolog\Logger('slim');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($logPath, \Monolog\Logger::ERROR));
        $errorMiddleware->setDefaultErrorHandler(function (
            \Psr\Http\Message\ServerRequestInterface $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($logger) {
            $logger->error($exception->getMessage(), ['exception' => $exception]);
            throw $exception;
        });
    }
    /**
     * Slim middleware to add CORS headers to every response
     */
    public static function corsMiddleware()
    {
        return function ($request, $handler) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        };
    }

    /**
     * Get Slim base path from environment or default
     */
    public static function getBasePath($default = '/api')
    {
        return getenv('SLIM_BASE_PATH') ?: $default;
    }

    /**
     * Get Slim error middleware config from environment
     */
    public static function getErrorMiddlewareConfig()
    {
        return [
            'displayErrorDetails' => getenv('SLIM_DISPLAY_ERROR_DETAILS') === 'true',
            'logErrors' => true,
            'logErrorDetails' => true,
        ];
    }
    /**
     * Render a success JSON response
     */
    public static function renderSuccessJson(Response $response): Response
    {
        return self::renderJSON($response, ['status' => 'success']);
    }

    /**
     * Render a raw JSON string response
     */
    public static function renderStringJson(Response $response, string $json, int $status = 200): Response
    {
        $response->getBody()->write($json);
        $response = $response->withHeader('Content-Type', 'application/json');
        if ($status !== 200) {
            $response = $response->withStatus($status);
        }
        return $response;
    }
    /**
     * Render an array as JSON response
     */
    public static function renderJson(Response $response, array $obj, int $status = 200): Response
    {
        return self::renderStringJson($response, json_encode($obj, JSON_THROW_ON_ERROR), $status);
    }

    public static function renderRedirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * Get an integer query parameter from the request
     */
    public static function getUriParamInt(Request $request, string $paramName): int
    {
    $val = SlimUtils::getUriParamString($request, $paramName);
    return intval($val);
    }

    /**
     * Get a string query parameter from the request
     */
    public static function getUriParamString(Request $request, string $paramName): string
    {
    $params = $request->getQueryParams();
    return $params[$paramName] ?? '';
    }

    public static function getRouteArgument(Request $request, string $name): string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // return NotFound for non-existent route
        if (!$route instanceof RouteInterface) {
            throw new HttpNotFoundException($request);
        }

        return $route->getArgument($name);
    }

    public static function renderPhoto(Response $response, Photo $photo): Response
    {
        $cacheProvider = new CacheProvider();
        $response = $cacheProvider->withEtag($response, $photo->getPhotoURI());
        $response = $response->withHeader('Content-type', $photo->getPhotoContentType());

        $response->getBody()->write($photo->getPhotoBytes());

        return $response;
    }
}
