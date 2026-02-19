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

```php
// Entry point: src/api/index.php or src/finance/index.php
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use DI\Container;

$container = new Container();
$container->set('view', fn() => new PhpRenderer($viewDir));
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware (LIFO order - last added runs FIRST!)
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new CorsMiddleware());         // Last added, runs first
$app->add(new AuthMiddleware());
$app->add(new VersionMiddleware());      // First added, runs last

// Routes
$app->group('/api', function(RouteCollectorProxy $group) {
    $group->get('/endpoint', fn($req, $res) => $res->withJson($data));
});

$app->run();
```

## Middleware Order (CRITICAL)

Slim 4 uses **Last In, First Out (LIFO)** ordering. Middleware added LAST executes FIRST:

```
Order Added               Execution Order
1. addBodyParsingMiddleware()   → 4th (processes body after routing)
2. addRoutingMiddleware()       → 3rd (routes request)
3. add(CorsMiddleware)          → 1st (first middleware to run)
4. add(AuthMiddleware)          → 2nd (runs after CORS)
5. add(VersionMiddleware)       → Last added, runs last
```

**Why it matters:**
- Auth should run before routes (check permissions)
- CORS should run first (allow/deny early)
- Body parsing last (only needed after routing)

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
