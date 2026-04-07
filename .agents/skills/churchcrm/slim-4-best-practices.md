---
title: "Slim 4 Best Practices"
intent: "Guidance for Slim 4 app setup, routing, middleware, and error handling"
tags: ["slim","middleware","routing","api","error-handling"]
prereqs: ["php-best-practices.md","routing-architecture.md"]
complexity: "intermediate"
---

# Skill: Slim 4 Best Practices

## Context
ChurchCRM uses Slim 4 for API routes and modern MVC features. This skill covers setup, routing, middleware, response handling, and dependency injection patterns.

---

## Application Setup Pattern

### MVC Modules — Use MvcAppFactory <!-- learned: 2026-04-07 -->

All HTML-serving Slim apps (admin, finance, groups, people, v2) **must** use
`MvcAppFactory::create()`. This centralises base path, body parsing, routing,
error handling (Tabler-styled HTML pages), and the standard middleware stack.

```php
// ✅ CORRECT — MVC entry points
use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app = MvcAppFactory::create('/finance', [
    'dashboardUrl'  => '/finance/',
    'dashboardText' => gettext('Back to Finance Dashboard'),
    'roleMiddleware' => FinanceRoleAuthMiddleware::class, // optional
]);

require __DIR__ . '/routes/dashboard.php';
$app->run();

// ❌ WRONG — Don't copy-paste AppFactory + middleware + error handler
$app = AppFactory::create();
$app->setBasePath(...);
// ... 50+ lines of boilerplate ...
```

Config options:
- `dashboardUrl` — path (relative to root) for error page "go back" button
- `dashboardText` — label for the button
- `roleMiddleware` — FQCN of role-auth middleware (omit for no role check)

`MvcAppFactory` does NOT include `VersionMiddleware` (only useful for API
response headers, invisible in browser).

### API Module — Direct AppFactory

`api/index.php` is the only entry point that uses `AppFactory` directly
with `registerDefaultJsonErrorHandler()`. It includes `VersionMiddleware`.

```php
// API entry point — NOT MvcAppFactory
$app = AppFactory::create();
$app->setBasePath(SlimUtils::getBasePath('/api'));
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
// Error middleware AFTER routing — Slim 4 LIFO means it wraps routing and catches HttpNotFoundException
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);
// ... custom middleware, routes, run
```

## Middleware Order (CRITICAL) <!-- learned: 2026-04-07 -->

Slim 4 uses **Last In, First Out (LIFO)** ordering. Middleware added LAST executes FIRST.

### The #1 Rule: Error middleware AFTER routing

`addErrorMiddleware()` **MUST** be called **AFTER** `addRoutingMiddleware()`. This is because
LIFO means it then wraps routing and catches `HttpNotFoundException` / `HttpMethodNotAllowedException`.
If error middleware is added BEFORE routing, routing exceptions escape uncaught → **raw PHP 500**.

```php
// ✅ CORRECT — error middleware wraps routing (LIFO)
$app->addBodyParsingMiddleware();  // 1st added → innermost
$app->addRoutingMiddleware();      // 2nd added → wraps body parsing
$errorMiddleware = $app->addErrorMiddleware(true, true, true);  // 3rd added → wraps routing (outermost of these 3)
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);   // or registerDefaultHtmlErrorHandler()

// Then custom middleware (added after error middleware, so they run BEFORE error handling)
$app->add(new CorsMiddleware());
$app->add(AuthMiddleware::class);

// ❌ WRONG — error middleware BEFORE routing → 404s become raw 500s
$errorMiddleware = $app->addErrorMiddleware(true, true, true);  // Added first
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();  // Added after → routing exceptions NOT caught by error middleware
```

### Full execution order (request flows top-to-bottom)

```
Code order (add)          LIFO execution order (request)
─────────────────         ──────────────────────────────
1. addBodyParsing()       5th — innermost (parses body)
2. addRouting()           4th — matches route or throws HttpNotFoundException
3. addErrorMiddleware()   3rd — catches exceptions from routing + inner middleware
4. add(CorsMiddleware)    2nd — CORS headers
5. add(AuthMiddleware)    1st — outermost, runs first on request
```

### Documenting Execution Order in Code <!-- learned: 2026-03-15 -->

Always add a comment documenting the **actual execution order** (not the add order) when the middleware stack is non-obvious. This prevents future developers from accidentally mis-ordering security/redirect middleware:

```php
// ✅ CORRECT - Document execution order, not add order
// Execution order: VersionMiddleware → AuthMiddleware → ChurchInfoRequiredMiddleware → CorsMiddleware
$app->add(new CorsMiddleware());              // Added 1st, runs LAST
$app->add(new ChurchInfoRequiredMiddleware()); // Added 2nd, runs 3rd
$app->add(AuthMiddleware::class);              // Added 3rd, runs 2nd
$app->add(VersionMiddleware::class);           // Added 4th, runs FIRST

// ❌ WRONG - Documents add order instead of execution order
// This causes confusion and security bugs when middleware is reordered
// Execution order: CorsMiddleware → ChurchInfoRequiredMiddleware → AuthMiddleware → VersionMiddleware
```

## Route Grouping & Prefix Patterns

### Basic Group with Middleware
```php
// Apply middleware to entire group
$app->group('/admin', function(RouteCollectorProxy $group) {
    $group->get('/users', UserController::class . ':list');
    $group->post('/users', UserController::class . ':create');
    $group->get('/users/{id}', UserController::class . ':show');
})->add(AdminRoleAuthMiddleware::class);
```

### Nested Groups
```php
$app->group('/api', function(RouteCollectorProxy $group) {
    $group->group('/admin', function(RouteCollectorProxy $adminGroup) {
        $adminGroup->post('/config', ConfigController::class . ':update');
        $adminGroup->get('/logs', LogController::class . ':list');
    })->add(AdminRoleAuthMiddleware::class);
    
    $group->group('/finance', function(RouteCollectorProxy $finGroup) {
        $finGroup->get('/reports', ReportController::class . ':index');
    })->add(FinanceRoleAuthMiddleware::class);
});
```

### Route Parameters
```php
$app->get('/person/{id}', function($req, $res, $args) {
    $personId = (int)$args['id'];  // Always cast to int
    $person = PersonQuery::create()->findOneById($personId);
    if ($person === null) {
        return $res->withStatus(404)->withJson(['error' => 'Not found']);
    }
    return $res->withJson(['data' => $person->toArray()]);
});
```

## Response Handling Patterns

### JSON Success Response
```php
use ChurchCRM\Slim\SlimUtils;

return SlimUtils::renderJSON($response, [
    'data' => $result,
    'message' => 'Operation successful'
]);
```

### Error Response with Logging
```php
try {
    $result = doWork();
    return SlimUtils::renderJSON($response, ['data' => $result]);
} catch (ValidationException $e) {
    return SlimUtils::renderErrorJSON(
        $response,
        gettext('Validation failed'),
        ['errors' => $e->getErrors()],
        400,
        $e,
        $request
    );
} catch (Throwable $e) {
    return SlimUtils::renderErrorJSON(
        $response,
        gettext('Operation failed'),
        [],
        500,
        $e,
        $request
    );
}
```

### File Download
```php
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$contentType = trim($finfo->file($filePath)) ?: 'application/octet-stream';

return $response
    ->withHeader('Content-Type', $contentType)
    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"')
    ->withBody(new \Slim\Psr7\Stream(fopen($filePath, 'rb')));
```

### PhpRenderer View Response
```php
use Slim\Views\PhpRenderer;

$view = $container->get('view');
return $view->render($response, 'users.php', [
    'sRootPath' => SystemURLs::getRootPath(),
    'sPageTitle' => gettext('Users'),
    'users' => $allUsers,
    'stats' => $userStats,
]);
```

## Dependency Injection via Constructor

**Pattern:**
```php
class UserService {
    public function __construct(
        private UserRepository $userRepo,
        private LoggerInterface $logger
    ) {}
    
    public function createUser($data): User {
        $this->logger->info('Creating user', ['email' => $data['email']]);
        return $this->userRepo->save($data);
    }
}

// Register in container
$container->set('UserService', fn(Container $c) => new UserService(
    $c->get('UserRepository'),
    $c->get('LoggerInterface')
));

// Use in routes
$app->post('/users', function($request, $response) use ($container) {
    $service = $container->get('UserService');
    $user = $service->createUser($request->getParsedBody());
    return SlimUtils::renderJSON($response, ['data' => $user]);
});
```

**Key Points:**
- NEVER use global `$container` directly
- Always inject dependencies via constructor
- Register services in container at startup
- Use type hints for IDE support

## Common Patterns

### Inline Closure Routes
```php
// ✅ CORRECT
$group->post('/endpoint', function($request, $response, $args) {
    $data = $request->getParsedBody();
    return $response->withJson(['success' => true]);
});

// ❌ WRONG - String references don't work in Slim 4
$group->post('/endpoint', 'ControllerClass::method');
```

### Email Handling (Always Catch Failures)
```php
// ✅ CORRECT - Log but don't crash
if (!mail($to, $subject, $body)) {
    error_log("Email failed to " . $to);
}
return $response->withJson(['data' => $result, 'message' => 'Created']);

// ❌ WRONG - Throws 500 error
if (!mail($to, $subject, $body)) {
    throw new Exception("Email failed");
}
```

### Null Safety
```php
// ✅ CORRECT - Null coalescing
echo $notification?->title ?? 'No Title';

// ❌ WRONG - TypeError if null
echo $notification->title;
```

## Error Handler Architecture <!-- learned: 2026-04-07 -->

ChurchCRM has two error handler registration methods in `SlimUtils`:

| Method | Used by | Renders |
|--------|---------|---------|
| `registerDefaultHtmlErrorHandler()` | `MvcAppFactory` (all MVC modules) | Tabler HTML page (Header + error-page.php + Footer) for browsers, JSON for AJAX/API |
| `registerDefaultJsonErrorHandler()` | `api/index.php` | JSON for API requests, HTML partial for browser requests |

Both methods:
- Call `setDefaultErrorHandler()` on the error middleware — **only one handler can exist**
- Log via `LoggerUtils::getAppLogger()` (not a separate Monolog logger)
- Sanitize messages via `sanitizeErrorMessage()`
- Map exceptions to proper HTTP status (404, 403, 405, 500)
- Detect API vs browser via `isApiRequest()` (Accept header, X-Requested-With, /api/ path)

### Never rethrow in error handlers <!-- learned: 2026-04-07 -->

Slim 4's `setDefaultErrorHandler` does NOT chain handlers — calling it
twice overwrites the first. But if the first handler **rethrows**, the
rethrown exception may bypass the replacement handler entirely and surface
as a raw 500.

```php
// ❌ WRONG — rethrow causes 500 even if a real handler is registered after
$errorMiddleware->setDefaultErrorHandler(function (...) use ($logger) {
    $logger->error($exception->getMessage());
    throw $exception; // ← THIS BREAKS EVERYTHING
});
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware); // overwritten but too late

// ✅ CORRECT — only one handler, no rethrow
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);
```

**`setupErrorLogger()` has been removed** — it was dead code that set a
rethrowing handler always overwritten by the real handler. All logging is
handled inside `registerDefaultHtmlErrorHandler` and
`registerDefaultJsonErrorHandler`.

## API Error Handling (Critical)

**ALWAYS use `SlimUtils::renderErrorJSON()` for API errors:**

```php
// Function signature
SlimUtils::renderErrorJSON(
    Response $response,        // Original $response object
    ?string $message = null,   // User-facing message (localized)
    array $extra = [],         // Extra data for response
    int $status = 500,         // HTTP status code
    ?\Throwable $exception = null,  // Exception for logging
    ?Request $request = null   // Request for context logging
): Response
```

**Behavior:**
- Server-side: Logs full exception details (trace, file, line)
- Client receives: Sanitized message only (no traces, file paths, credentials)
- Message is automatically masked for sensitive patterns
- Never throws exceptions from route handlers

---

## Related Knowledge
- **Routing & Middleware**: See Admin System Pages section in copilot-instructions.md
- **Authorization**: See authorization-security.md skill
- **API Development**: See api-development.md skill
