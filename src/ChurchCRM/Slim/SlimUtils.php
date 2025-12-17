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
        return self::renderJSON($response, ['success' => true], $status);
    }

    /**
     * Render a standard error JSON response
     * Ensures a consistent shape for all error responses and sanitizes messages
     */
    public static function renderErrorJSON(Response $response, ?string $message = null, array $extra = [], int $status = 500, ?\Throwable $exception = null, ?Request $request = null): Response
    {
        $default = gettext('An error occurred. Please contact your system administrator.');
        $msg = $message ?: $default;

        // Sanitize the provided message to avoid leaking credentials
        if (preg_match('/(password|credential|secret|api[_-]?key|token|username|user|host|localhost|127\.0\.0|\d{1,3}\.\d{1,3})/i', $msg)) {
            $msg = $default;
        }

        // Centralized logging of the exception and request context when available
        try {
            $logger = LoggerUtils::getAppLogger();
            $logContext = $extra;
            if ($exception !== null) {
                $logContext['exception_class'] = get_class($exception);
                $logContext['error'] = $exception->getMessage();
                $logContext['file'] = $exception->getFile();
                $logContext['line'] = $exception->getLine();
                $logContext['trace'] = $exception->getTraceAsString();
            }
            if ($request !== null) {
                $logContext['method'] = $request->getMethod();
                $logContext['path'] = $request->getUri()->getPath();
                $logContext['query'] = $request->getUri()->getQuery();
                $logContext['ip'] = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
                $logContext['user_agent'] = $request->getHeaderLine('User-Agent');
            }
            $logger->error($msg, $logContext);
        } catch (\Throwable $logEx) {
            // If logging fails, do not expose details to the client; fail silently
        }

        $payload = array_merge(['success' => false, 'message' => $msg], $extra);
        return self::renderJSON($response, $payload, $status);
    }

    /**
     * (removed lowercase alias) Use `renderStringJSON` instead
     */

    /**
     * Registers custom error, not found, and not allowed handlers on the Slim container
     * @deprecated Slim 3 only. Use Slim 4 error middleware instead.
     */
    public static function registerCustomErrorHandlers($container)
    {
        // Error handler: returns JSON with sanitized error details (Slim3 compatibility)
        $container->set('errorHandler', fn ($container): \Closure => function ($request, $response, $exception) use ($container) {
            $data = [
                'code'    => $exception->getCode(),
                'message' => self::sanitizeErrorMessage($exception),
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
        return [];
    }

    /**
     * Helper to write a JSON string to the response body
     */
    public static function renderStringJSON(Response $response, string $json, int $status = 200): Response
    {
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
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
            // Log full error details to disk for debugging (includes sensitive info)
            // This is only visible to administrators, not to users
            $requestContext = [
                'exception' => $exception,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'query' => $request->getUri()->getQuery(),
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $request->getHeaderLine('User-Agent')
            ];
            $logger->error('Uncaught exception: ' . $exception->getMessage(), $requestContext);
            
            $response = new Psr7Response();
            
            // Determine appropriate HTTP status code based on exception type
            $statusCode = 500;
            if ($exception instanceof HttpNotFoundException) {
                $statusCode = 404;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $statusCode = 405;
            }
            
            // Sanitize error message to prevent credential disclosure
            $sanitizedMessage = self::sanitizeErrorMessage($exception);
            
            // Include HTTP method and path in error response for debugging
            $errorResponse = [
                'error' => $sanitizedMessage,
                'code' => $exception->getCode(),
                'request' => [
                    'method' => $request->getMethod(),
                    'path' => $request->getUri()->getPath()
                ]
            ];
            
            // Do NOT include file/line/trace in responses to avoid leaking internals
            
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        });
    }

    /**
     * Render an array as JSON response
     */
    /**
     * Render an array as JSON response (canonical camel-case)
     */
    public static function renderJSON(Response $response, array $obj, int $status = 200): Response
    {
        return self::renderStringJSON($response, json_encode($obj, JSON_THROW_ON_ERROR), $status);
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
     * Sanitize error messages to prevent database credential disclosure
     * Removes sensitive information like passwords, hosts, and connection strings
     * 
     * @param Throwable $exception The exception to sanitize
     * @return string Sanitized error message safe for user display
     */
    public static function sanitizeErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        
        // For database-related exceptions, return generic message
        if ($exception instanceof \PDOException || 
            stripos($exception->getFile(), 'propel') !== false ||
            stripos($message, 'sql') !== false ||
            stripos($message, 'database') !== false) {
            return 'A database error occurred. Please contact your system administrator.';
        }
        
        // For all other exceptions, don't return the actual message in production
        // Only return message if it doesn't contain sensitive info patterns
        if (preg_match('/(password|credential|secret|api[_-]?key|token|username|user|host|localhost|127\.0\.0|\d{1,3}\.\d{1,3})/i', $message)) {
            return 'An error occurred. Please contact your system administrator.';
        }
        
        return $message;
    }

    /**
     * Get an integer query parameter from the request URI
     */
    public static function getURIParamInt(Request $request, string $paramName): int
    {
        $value = self::getUriParamString($request, $paramName);
        return $value !== '' ? (int) $value : 0;
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
