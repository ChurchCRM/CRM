---
title: "Slim 4 Best Practices"
intent: "Guidance for Slim 4 app setup, routing, middleware, and error handling"
tags: ["slim","middleware","routing","api","error-handling"]
prereqs: ["[[php-best-practices]]","[[routing-architecture]]"]
complexity: "intermediate"
---

# Slim 4 Best Practices

ChurchCRM uses Slim 4 for API routes and MVC modules. This card covers app setup, middleware order, route grouping, responses, and error handling.

## Application setup

### MVC modules: `MvcAppFactory`

Every HTML-serving module (admin, finance, groups, people, v2, ...) calls `MvcAppFactory::create()`. It sets base path, body parsing, routing, the Tabler HTML error handler, and the standard middleware stack. It omits `VersionMiddleware`, which only adds API response headers.

```php
use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app = MvcAppFactory::create('/finance', [
    'dashboardUrl'  => '/finance/',
    'dashboardText' => gettext('Back to Finance Dashboard'),
    'roleMiddleware' => FinanceRoleAuthMiddleware::class, // optional
]);

require __DIR__ . '/routes/dashboard.php';
$app->run();
```

Config options: `dashboardUrl` (error page "go back" path), `dashboardText` (button label), `roleMiddleware` (role-auth FQCN; omit for none), `displayErrorDetails`, `errorHandler` (module-specific handler that replaces the shared Tabler error page).

### API module: `AppFactory`

`src/api/index.php` uses `AppFactory` directly with `registerDefaultJsonErrorHandler()` and `VersionMiddleware`.

```php
$app = AppFactory::create();
$app->setBasePath(SlimUtils::getBasePath('/api'));
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);
```

## Middleware Order

Slim 4 is Last In, First Out: the middleware added last runs first.

Call `addErrorMiddleware()` after `addRoutingMiddleware()` so the error middleware wraps routing and catches `HttpNotFoundException` / `HttpMethodNotAllowedException`. Add custom middleware after the error middleware so it runs before error handling.

```php
$app->addBodyParsingMiddleware();                               // innermost
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);   // or registerDefaultHtmlErrorHandler()

$app->add(new CorsMiddleware());
$app->add(AuthMiddleware::class);                               // outermost, runs first
```

Comment the execution order, not the add order, whenever the stack is non-obvious:

```php
// Execution order: VersionMiddleware -> AuthMiddleware -> CorsMiddleware
$app->add(new CorsMiddleware());      // added 1st, runs last
$app->add(AuthMiddleware::class);     // added 2nd, runs 2nd
$app->add(VersionMiddleware::class);  // added 3rd, runs first
```

## Route grouping

Apply middleware to a whole group with `->add()` after the closure; groups nest.

```php
$app->group('/api', function (RouteCollectorProxy $group): void {
    $group->group('/admin', function (RouteCollectorProxy $adminGroup): void {
        $adminGroup->get('/logs', function (Request $request, Response $response): Response { ... });
    })->add(AdminRoleAuthMiddleware::class);
});
```

Use inline closures for handlers. Cast route ids: `$personId = (int) $args['id'];`, then look the record up with a Propel query and return a 404 through `SlimUtils::renderErrorJSON()` when it is `null`.

## Response handling

### JSON

```php
use ChurchCRM\Slim\SlimUtils;

return SlimUtils::renderJSON($response, ['data' => $result, 'message' => gettext('Operation successful')]);
```

`SlimUtils` also offers `renderSuccessJSON()`, `renderStringJSON()`, `renderRedirect()`, and `renderPhoto()`.

### JSON errors

```php
try {
    $result = doWork();
    return SlimUtils::renderJSON($response, ['data' => $result]);
} catch (ValidationException $e) {
    return SlimUtils::renderErrorJSON($response, gettext('Validation failed'), ['errors' => $e->getErrors()], 400, $e, $request);
} catch (Throwable $e) {
    return SlimUtils::renderErrorJSON($response, gettext('Operation failed'), [], 500, $e, $request);
}
```

`renderErrorJSON(Response $response, ?string $message = null, array $extra = [], int $status = 500, ?\Throwable $exception = null, ?Request $request = null)` logs the full exception server-side and sends the client a sanitized, localized message with sensitive patterns masked. Route handlers return this response and let `renderErrorJSON()` own error output.

### File download

```php
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$contentType = trim($finfo->file($filePath)) ?: 'application/octet-stream';

return $response
    ->withHeader('Content-Type', $contentType)
    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"')
    ->withBody(new \Slim\Psr7\Stream(fopen($filePath, 'rb')));
```

### PhpRenderer views

Construct the renderer in the route, pointing at the module's `views/` directory:

```php
use Slim\Views\PhpRenderer;

$view = new PhpRenderer(__DIR__ . '/../views/');
return $view->render($response, 'users.php', [
    'sRootPath' => SystemURLs::getRootPath(),
    'sPageTitle' => gettext('Users'),
    'users' => $allUsers,
]);
```

## Obtaining services

Services are constructed where they are used; there is no PSR-11 container and no `$container` variable in a route file. Unpack and cast the parsed body in the route before passing values to the service.

```php
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\InputUtils;

function adminUserEditorNew(Request $request, Response $response): Response
{
    $userService = new UserService();

    $body     = (array) $request->getParsedBody();
    $personId = (int) ($body['PersonID'] ?? 0);
    $userName = InputUtils::sanitizeText((string) ($body['UserName'] ?? ''));
    $perms    = $userService->normalizeAccessMode($body);

    $userService->createUser($personId, $perms, $userName);
    // ...
}
```

Services with only static methods (`AppIntegrityService`, `LocaleService`, ...) are called statically. Rules: [`service-layer.md`](./service-layer.md).

## Common patterns

Email failures log and the request continues:

```php
if (!mail($to, $subject, $body)) {
    error_log('Email failed to ' . $to);
}
return SlimUtils::renderJSON($response, ['data' => $result, 'message' => gettext('Created')]);
```

Null safety uses the nullsafe and coalescing operators: `echo $notification?->title ?? 'No Title';`

## Error handler architecture

| `SlimUtils` method | Used by | Renders |
|--------------------|---------|---------|
| `registerDefaultHtmlErrorHandler()` | `MvcAppFactory` | Tabler HTML page (Header + `error-page.php` + Footer) for browsers, JSON for AJAX/API |
| `registerDefaultJsonErrorHandler()` | `src/api/index.php` | JSON for API requests, HTML partial for browser requests |

Both:

- call `setDefaultErrorHandler()` on the error middleware; one handler exists per app, and a later call overwrites the earlier one
- log via `LoggerUtils::getAppLogger()`
- sanitize messages via `sanitizeErrorMessage()`
- map exceptions to HTTP status (404, 403, 405, 500)
- detect API vs browser via `isApiRequest()` (Accept header, `X-Requested-With`, `/api/` path)

Register exactly one handler per app, with no rethrow inside it. All logging happens inside the two `registerDefault*` methods.

## Related

- [Admin MVC Migration](./admin-mvc-migration.md)
- `authorization-security.md`
- `api-development.md`
