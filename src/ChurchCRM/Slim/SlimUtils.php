<?php
namespace ChurchCRM\Slim;

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Response as Psr7Response;
use Slim\Routing\RouteContext;
use Throwable;


class SlimUtils
{
    /**
     * Render a standard success JSON response
     */
    public static function renderSuccessJSON(Response $response, int $status = 200): Response
    {
        return self::renderJson($response, ['success' => true], $status);
    }

    /**
     * Helper to write a JSON string to the response body
     */
    public static function renderStringJson(Response $response, string $json, int $status = 200): Response
    {
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Registers custom error, not found, and not allowed handlers on the Slim container
     * @deprecated Slim 3 only. Use Slim 4 error middleware instead.
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
    public static function setupErrorLogger($errorMiddleware)
    {
        $logPath = LoggerUtils::buildLogFilePath('slim-error');
        $logger = new Logger('slim');
        $logger->pushHandler(new StreamHandler($logPath, LoggerUtils::getLogLevel()));
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($logger) {
            $logger->error($exception->getMessage(), ['exception' => $exception]);
            throw $exception;
        });
    }

    /**
     * Get Slim base path from environment or calculate from SystemURLs root path
     * This ensures Slim routes work correctly whether installed at root (/) or in a subdirectory (/churchcrm)
     * 
     * @param string $endpoint The Slim application endpoint (/api or /v2) - REQUIRED
     * @return string The complete base path including subdirectory if applicable
     */
    public static function getBasePath(string $endpoint)
    {
        // Allow environment override for testing/special deployments
        if ($envPath = getenv('SLIM_BASE_PATH')) {
            return $envPath;
        }
        
        // Get the root path from SystemURLs (configured in Config.php as $sRootPath)
        // Examples: '' (root install), '/churchcrm', '/crm', etc.
        try {
            $rootPath = SystemURLs::getRootPath();
            
            // Combine root path with endpoint
            // If root is empty string (installed at /), just return endpoint
            // If root is /churchcrm, return /churchcrm/api or /churchcrm/v2
            return $rootPath . $endpoint;
        } catch (Exception $e) {
            // If SystemURLs not initialized yet, fall back to endpoint only
            // This shouldn't happen in normal operation but provides safety
            return $endpoint;
        }
    }

    /**
     * Get Slim error middleware config from environment
     * @return array
     */
    public static function getErrorMiddlewareConfig(): array
    {
        // Placeholder for future config logic
        return [];
    }

    /**
     * Registers a default Slim4 error handler that returns JSON error details
     */
    public static function registerDefaultJsonErrorHandler($errorMiddleware)
    {
        $logger = LoggerUtils::getAppLogger();
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($logger) {
            // Include HTTP method and path in log context
            $requestContext = [
                'exception' => $exception,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'query' => $request->getUri()->getQuery()
            ];
            $logger->error($exception->getMessage(), $requestContext);
            
            $response = new Psr7Response();
            
            // Determine appropriate HTTP status code based on exception type
            $statusCode = 500;
            if ($exception instanceof HttpNotFoundException) {
                $statusCode = 404;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $statusCode = 405;
            }
            
            // Include HTTP method and path in error response for debugging
            $errorResponse = [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'request' => [
                    'method' => $request->getMethod(),
                    'path' => $request->getUri()->getPath()
                ]
            ];
            
            // Include file/line details if error details should be displayed
            if ($displayErrorDetails) {
                $errorResponse['file'] = $exception->getFile();
                $errorResponse['line'] = $exception->getLine();
            }
            
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        });
    }

    /**
     * Render an array as JSON response
     */
    public static function renderJson(Response $response, array $obj, int $status = 200): Response
    {
        return self::renderStringJson($response, json_encode($obj, JSON_THROW_ON_ERROR), $status);
    }

    /**
     * Render a redirect response
     */
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
        $val = self::getUriParamString($request, $paramName);
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

    /**
     * Get a route argument from the request
     * @throws HttpNotFoundException
     */
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

    /**
     * Render a photo response
     */
    public static function renderPhoto(Response $response, Photo $photo): Response
    {
        // Set content type - ensure it's a valid string
        $contentType = $photo->getPhotoContentType();
        if ($contentType && is_string($contentType)) {
            $response = $response->withHeader('Content-Type', trim($contentType));
        } else {
            $response = $response->withHeader('Content-Type', 'application/octet-stream');
        }
        
        // Write photo bytes to response body
        $response->getBody()->write($photo->getPhotoBytes());
        
        return $response;
    }
}
