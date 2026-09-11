<?php
namespace ChurchCRM\Slim;

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use Exception;
use Propel\Runtime\Exception\PropelException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Response as Psr7Response;
use Slim\Routing\RouteContext;
use Throwable;


class SlimUtils
{
    /**
     * Patterns that indicate a message carries a secret *value*, rather than
     * merely containing an English word like "user", "host" or "token".
     *
     * The previous rule was a bare word list (`/(password|...|user|host|\d{1,3}\.\d{1,3})/i`),
     * unanchored, so it discarded ordinary messages: `User not found`,
     * `Ghostwriter field is required`, `Value must be between 1.5 and 3.5`.
     * Each pattern here requires credential-like *context* — an assignment, a
     * connection string, key material, or a full address (#9737).
     */
    private const SENSITIVE_VALUE_PATTERNS = [
        // password=…, api_key: …, Authorization: Bearer … — a credential name
        // followed by an assignment and a value.
        '/\\b(?:pass(?:word|wd)?|pwd|secret|credentials?|api[_-]?key|(?:access|refresh|auth|bearer|csrf|session)[_-]?token|token|authorization|private[_-]?key|client[_-]?secret)\\b\\s*[:=]\\s*\\S/i',
        // DSN / connection-string fragments: mysql:host=db;dbname=x;user=y
        '/\\b(?:host|hostname|dbname|unix_socket|user|username|uid)\\s*=\\s*\\S/i',
        // Credentials embedded in a URL: scheme://user:pass@host
        '#\\b[a-z][a-z0-9+.-]*://[^\\s/@]+:[^\\s/@]+@#i',
        // PEM key material
        '/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----/',
        // JSON Web Token
        '/\\beyJ[A-Za-z0-9_-]{8,}\\.[A-Za-z0-9_-]{8,}\\.[A-Za-z0-9_-]+/',
        // A long opaque run — API keys, session ids, base64 blobs. No English
        // word or identifier in a user-facing message reaches 40 characters.
        '/\\b[A-Za-z0-9_-]{40,}\\b/',
        // A complete IPv4 address (the old rule matched any two decimals,
        // so it redacted "between 1.5 and 3.5").
        '/\\b(?:\\d{1,3}\\.){3}\\d{1,3}\\b/',
    ];

    /**
     * A run of standard-base64 characters long enough to be key material.
     * `SENSITIVE_VALUE_PATTERNS` covers the URL-safe alphabet; this adds the
     * `+`, `/` and `=` that standard base64 uses, which an unlabelled
     * credential such as an AWS secret access key
     * (`wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY`) is built from.
     */
    private const OPAQUE_BASE64_RUN = '#[A-Za-z0-9+/=_-]{40,}#';

    /**
     * True when a standard-base64 run in the message looks like key material
     * rather than a file path.
     *
     * Simply adding `+/=` to the URL-safe pattern is not safe: it makes any
     * absolute or deep relative path of 40+ characters match, so
     * "Failed to open /var/www/churchcrm/src/ChurchCRM/Service/PersonService"
     * would be replaced wholesale by the generic message — reintroducing
     * exactly the over-redaction #9737 set out to remove.
     *
     * Paths are built from dictionary words, so they always contain a long
     * run of consecutive lowercase letters ("churchcrm", "templates",
     * "local"); a base64 blob of the same length essentially never does.
     * That single discriminator separates the two cleanly.
     */
    private static function containsOpaqueBase64Run(string $message): bool
    {
        if (preg_match_all(self::OPAQUE_BASE64_RUN, $message, $matches) < 1) {
            return false;
        }

        foreach ($matches[0] as $run) {
            // No `+`, `/` or `=` means the URL-safe pattern above already
            // decided this run; nothing to add here.
            if (preg_match('#[+/=]#', $run) !== 1) {
                continue;
            }
            // A five-letter lowercase word fragment marks this as prose/path.
            if (preg_match('/[a-z]{5}/', $run) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * True when the message looks like it carries a secret value and must not
     * be shown to the caller.
     */
    public static function containsSensitiveValue(string $message): bool
    {
        foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return self::containsOpaqueBase64Run($message);
    }

    /**
     * Build the canonical /api error payload.
     *
     * One shape for every error response (#9737). It is a superset of the
     * three shapes that used to be produced, so existing consumers keep
     * working: `message` for the `responseJSON.message` readers (the majority,
     * plus CRMJSOM's `message || error || msg` fallback), `error` for
     * `responseJSON.error` readers (DepositSlipEditor), `code` for anything
     * branching on the status, and `success: false` so a response can be
     * tested without inspecting the HTTP status.
     *
     * New code should read `message`.
     *
     * The canonical keys are merged **last** on purpose: `error` is an alias of
     * `message` and must never carry a different value, or a
     * `responseJSON.error` consumer and a `responseJSON.message` consumer would
     * see two different errors for the same response. A colliding key in
     * `$extra` is therefore dropped rather than allowed to break the alias.
     *
     * @param array<string, mixed> $extra additional keys merged into the payload
     * @return array<string, mixed>
     */
    public static function buildErrorPayload(string $message, int $code, array $extra = []): array
    {
        return array_merge(
            $extra,
            [
                'success' => false,
                'message' => $message,
                'error'   => $message,
                'code'    => $code,
            ]
        );
    }

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

        // Sanitize the provided message to avoid leaking credential values.
        // Only value-shaped secrets are redacted — an ordinary message such as
        // "User not found" must reach the caller intact (#9737).
        if (self::containsSensitiveValue($msg)) {
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

        return self::renderJSON($response, self::buildErrorPayload($msg, $status, $extra), $status);
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
                ->write(json_encode($data));
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
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $statusCode = 401;
            } elseif ($exception instanceof HttpForbiddenException) {
                $statusCode = 403;
            } elseif ($exception instanceof HttpBadRequestException) {
                $statusCode = 400;
            }

            // Sanitize error message to prevent credential disclosure
            $sanitizedMessage = self::sanitizeErrorMessage($exception);

            $path = $request->getUri()->getPath();

            if (self::isApiRequest($request)) {
                // Include HTTP method and path in error response for debugging
                $errorResponse = self::buildErrorPayload($sanitizedMessage, $statusCode, [
                    'request' => [
                        'method' => $request->getMethod(),
                        'path' => $path
                    ]
                ]);

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
                $errorResponse = self::buildErrorPayload(
                    gettext('An error occurred while rendering the error page.'),
                    $statusCode
                );
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
            }
        });
    }

    /**
     * Detect whether a request expects a JSON (API) response.
     * Checks Accept header for application/json, X-Requested-With for AJAX,
     * and path for /api/ segments.
     */
    private static function isApiRequest(Request $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }
        $path = $request->getUri()->getPath();
        return (bool) preg_match('#(^|/)api(/|$)#i', $path);
    }

    /**
     * Register a default error handler for HTML-serving MVC modules.
     *
     * Browser requests render a full Tabler-styled error page (Header + shared
     * error-page.php partial + Footer).  API / AJAX requests fall back to JSON.
     *
     * @param object $errorMiddleware  Slim ErrorMiddleware instance
     * @param string $dashboardUrl     Absolute URL for the "go back" button (relative to root)
     * @param string $dashboardText    Label for the "go back" button
     */
    public static function registerDefaultHtmlErrorHandler(
        $errorMiddleware,
        string $dashboardUrl,
        string $dashboardText
    ): void {
        $logger = LoggerUtils::getAppLogger();

        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($logger, $dashboardUrl, $dashboardText) {
            // Determine HTTP status code
            $statusCode = 500;
            if ($exception instanceof HttpNotFoundException) {
                $statusCode = 404;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $statusCode = 405;
            } elseif ($exception instanceof \Slim\Exception\HttpForbiddenException) {
                $statusCode = 403;
            } elseif ($exception instanceof HttpBadRequestException) {
                $statusCode = 400;
            }

            // Log: info for 4xx, error for 5xx
            $logContext = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ];
            if ($statusCode >= 500) {
                $logContext['trace'] = $exception->getTraceAsString();
                $logger->error('MVC error', $logContext);
            } else {
                $logger->info('MVC ' . $statusCode, $logContext);
            }

            $response = new Psr7Response();

            // API / AJAX requests get JSON
            if (self::isApiRequest($request)) {
                $errorResponse = self::buildErrorPayload(self::sanitizeErrorMessage($exception), $statusCode, [
                    'request' => [
                        'method' => $request->getMethod(),
                        'path' => $request->getUri()->getPath(),
                    ],
                ]);
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
            }

            // Browser requests get full Tabler HTML error page
            try {
                $code = $statusCode;
                $title = match (true) {
                    $statusCode === 404 => gettext('Page Not Found'),
                    $statusCode === 403 => gettext('Permission Required'),
                    $statusCode === 405 => gettext('Method Not Allowed'),
                    $statusCode >= 500 => gettext('Server Error'),
                    default => gettext('Error'),
                };
                $message = self::sanitizeErrorMessage($exception);
                $returnUrl = SystemURLs::getRootPath() . $dashboardUrl;
                $returnText = $dashboardText;
                $extraHtml = '';

                // Dev-mode technical details
                if ($displayErrorDetails && $statusCode >= 500) {
                    $escaped = htmlspecialchars($exception->getMessage());
                    $nonce = SystemURLs::getCSPNonce();
                    $extraHtml = '<div class="mb-4"><details class="card card-outline border-secondary">'
                        . '<summary class="card-header cursor-pointer d-flex justify-content-between align-items-center">'
                        . '<span><i class="fa-solid fa-code"></i> ' . gettext('Technical Details') . ' (Development Mode)</span>'
                        . '<button type="button" class="btn btn-sm btn-outline-secondary copy-error-btn" style="border: none; padding: 0.25rem 0.5rem;" title="' . gettext('Copy error message') . '">'
                        . '<i class="fa-solid fa-copy"></i></button></summary>'
                        . '<div class="card-body"><pre class="mb-0"><code id="errorMessage">' . $escaped . '</code></pre></div>'
                        . '</details></div>'
                        . '<script nonce="' . $nonce . '">'
                        . 'document.querySelector(".copy-error-btn")?.addEventListener("click", function(e) {'
                        . 'e.stopPropagation();'
                        . 'const errorText = document.getElementById("errorMessage")?.textContent || "";'
                        . 'navigator.clipboard.writeText(errorText).then(() => {'
                        . 'const btn = this;'
                        . 'const originalHTML = btn.innerHTML;'
                        . 'btn.innerHTML = \'<i class="fa-solid fa-check"></i>\';'
                        . 'setTimeout(() => {btn.innerHTML = originalHTML;}, 2000);'
                        . '}).catch(() => { /* clipboard unavailable — no-op */ });'
                        . '});'
                        . '</script>';
                }

                $sPageTitle = $title;

                ob_start();
                require SystemURLs::getDocumentRoot() . '/Include/Header.php';
                require SystemURLs::getDocumentRoot() . '/v2/templates/common/error-page.php';
                require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
                $html = ob_get_clean();

                $response->getBody()->write($html);
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'text/html');
            } catch (Throwable $renderEx) {
                // If HTML rendering fails, fall back to JSON
                $logger->error('Error page render failed', [
                    'render_error' => $renderEx->getMessage(),
                    'original_error' => $exception->getMessage(),
                ]);
                $fallback = self::buildErrorPayload(gettext('An error occurred.'), $statusCode);
                $response->getBody()->write(json_encode($fallback));
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
            }
        });
    }

    /**
     * Render an array as JSON response (canonical camel-case)
     */
    public static function renderJSON(Response $response, array $obj, int $status = 200): Response
    {
        return self::renderStringJSON($response, json_encode($obj), $status);
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
        // HTTP exceptions carry intentionally user-facing messages (e.g. "Invalid login or password")
        // set by route handlers — pass through as-is without redaction.
        if ($exception instanceof \Slim\Exception\HttpException) {
            return $exception->getMessage();
        }

        $message = $exception->getMessage();

        // For database-related exceptions, return generic message.
        // The ORM exception class is the reliable signal: the vendor directory
        // is `perplorm/perpl`, so the old `stripos($file, 'propel')` check never
        // fired, and a failing INSERT is thrown from the generated model under
        // src/ChurchCRM/model/. That let Propel leak the raw statement to the
        // client (#9737, seen via #9736).
        if ($exception instanceof \PDOException ||
            $exception instanceof PropelException ||
            stripos($exception->getFile(), 'propel') !== false ||
            stripos($exception->getFile(), 'perpl') !== false ||
            preg_match('/\\b(SQLSTATE|INSERT INTO|UPDATE .+ SET|DELETE FROM|SELECT .+ FROM)\\b/i', $message) === 1 ||
            stripos($message, 'sql') !== false ||
            stripos($message, 'database') !== false) {
            return 'A database error occurred. Please contact your system administrator.';
        }

        // For unexpected exceptions, redact only messages that carry a secret
        // value — not every message containing the word "user" (#9737).
        if (self::containsSensitiveValue($message)) {
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
