<?php
namespace ChurchCRM\Slim;

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use Exception;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
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
                $logContext['exception_class'] = $exception::class;
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
        $logger = new Logger('slim');
        // Slim errors should be logged at WARNING level minimum to capture all errors
        // regardless of system log level configuration
        $logLevel = max(LoggerUtils::getLogLevel()->value, Level::Warning->value);
        $handler = new RotatingFileHandler(LoggerUtils::buildRotatingLogBasePath('slim-error'), LoggerUtils::LOG_RETENTION_DAYS, $logLevel);
        $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
        $handler->setFormatter(LoggerUtils::createFormatter());
        $logger->pushHandler($handler);
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
     * Determines whether the given request is an API/JSON request.
     * Returns true when the Accept header prefers JSON or the path contains /api/.
     */
    private static function isApiRequest(Request $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        $path         = $request->getUri()->getPath();
        return stripos($acceptHeader, 'application/json') !== false
            || preg_match('#(^|/)api(/|$)#i', $path) === 1;
    }

    /**
     * Registers a default Slim4 error handler that returns Tabler-styled HTML error pages
     * (with the standard nav shell) for browser requests, and JSON for API/JSON requests.
     *
     * Requires the shared template at: {docRoot}/v2/templates/common/error-page.php
     * and the standard Header/Footer includes. Do not move those files without updating
     * the require paths in this method.
     *
     * @param mixed  $errorMiddleware Slim error middleware instance
     * @param string $dashboardUrl    URL for the primary "Return to …" action button
     * @param string $dashboardText   Label text for the primary action button
     */
    public static function registerDefaultHtmlErrorHandler(
        $errorMiddleware,
        string $dashboardUrl = '',
        string $dashboardText = ''
    ): void {
        $errorMiddleware->setDefaultErrorHandler(function (
            Request   $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,       // required by Slim's error handler signature
            bool $logErrorDetails  // required by Slim's error handler signature
        ) use ($dashboardUrl, $dashboardText) {
            $logger = LoggerUtils::getAppLogger();

            // Determine HTTP status code
            $statusCode = 500;
            if ($exception instanceof HttpNotFoundException) {
                $statusCode = 404;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $statusCode = 405;
            }

            $path = $request->getUri()->getPath();

            // Log with appropriate level — 5xx are errors, 4xx are informational
            if ($statusCode >= 500) {
                $logger->error('HTTP ' . $statusCode . ' error', [
                    'exception' => $exception::class,
                    'message'   => $exception->getMessage(),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'path'      => $path,
                ]);
            } else {
                $logger->info('HTTP ' . $statusCode, ['path' => $path]);
            }

            $response = new Psr7Response();

            // For API sub-paths or JSON Accept header, return JSON
            if (self::isApiRequest($request)) {
                $sanitizedMessage = self::sanitizeErrorMessage($exception);
                $payload = json_encode([
                    'error'   => $sanitizedMessage,
                    'code'    => $exception->getCode(),
                    'request' => ['method' => $request->getMethod(), 'path' => $path],
                ], JSON_THROW_ON_ERROR);
                $response->getBody()->write($payload);
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
            }

            // Render Tabler-styled full HTML page with nav shell (Header + error partial + Footer)
            $rootPath   = SystemURLs::getRootPath();
            $returnUrl  = $dashboardUrl ?: ($rootPath . '/v2/dashboard');
            $returnText = $dashboardText ?: gettext('Return to Dashboard');

            $code    = $statusCode;
            $title   = match ($statusCode) {
                404     => gettext('Page Not Found'),
                405     => gettext('Method Not Allowed'),
                default => gettext('Server Error'),
            };
            $message = match ($statusCode) {
                404     => gettext('The page you were looking for could not be found.'),
                405     => gettext('The HTTP method used is not allowed for this route.'),
                default => gettext('An unexpected error occurred. Please contact your administrator.'),
            };

            $extraHtml = '';
            if ($displayErrorDetails && $statusCode >= 500) {
                $sanitizedMessage = self::sanitizeErrorMessage($exception);
                $extraHtml = '<details class="card card-outline border-secondary mt-3 text-start">'
                    . '<summary class="card-header cursor-pointer"><i class="ti ti-code me-1"></i>'
                    . gettext('Technical Details') . ' (' . gettext('Development Mode') . ')</summary>'
                    . '<div class="card-body"><pre class="mb-0"><code>'
                    . htmlspecialchars($sanitizedMessage)
                    . '</code></pre></div></details>';
            }

            // $sPageTitle is read by Header.php via variable scope (extract/include)
            $sPageTitle = $title;
            $docRoot    = SystemURLs::getDocumentRoot();

            try {
                ob_start();
                require $docRoot . '/Include/Header.php';
                require $docRoot . '/v2/templates/common/error-page.php';
                require $docRoot . '/Include/Footer.php';
                $html = ob_get_clean();
                $response->getBody()->write($html);
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'text/html');
            } catch (Throwable $renderEx) {
                // If page rendering fails, fall back to JSON so the client gets a response
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $logger->error('Failed to render HTML error page', ['exception' => $renderEx::class]);
                $payload = json_encode(['error' => 'An error occurred while rendering the error page.'], JSON_THROW_ON_ERROR);
                $response->getBody()->write($payload);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });
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
            bool $logErrors,       // required by Slim's error handler signature
            bool $logErrorDetails  // required by Slim's error handler signature
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

            $path = $request->getUri()->getPath();
            if (self::isApiRequest($request)) {
                // Include HTTP method and path in error response for debugging
                $errorResponse = [
                    'error' => $sanitizedMessage,
                    'code' => $exception->getCode(),
                    'request' => [
                        'method' => $request->getMethod(),
                        'path' => $path
                    ]
                ];

                $response->getBody()->write(json_encode($errorResponse));
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
            }

            // For non-API (MVC) requests render a skinned HTML error page using shared partial
            try {
                // Prepare variables expected by the partial
                $code = $statusCode;
                $title = ($statusCode >= 500) ? gettext('Server Error') : gettext('Not Found');
                $message = $sanitizedMessage;
                $returnUrl = SystemURLs::getRootPath() . '/v2/dashboard';
                $returnText = gettext('Return to Dashboard');
                $extraHtml = '';

                ob_start();
                // Include the shared error partial (path relative to src/ChurchCRM/Slim)
                require __DIR__ . '/../../v2/templates/common/error-page.php';
                $html = ob_get_clean();

                $response->getBody()->write($html);
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'text/html');
            } catch (Throwable $e) {
                // If rendering the HTML page fails, fallback to JSON to ensure client receives an error
                $errorResponse = [
                    'error' => 'An error occurred while rendering the error page.',
                    'code' => $exception->getCode(),
                ];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
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
