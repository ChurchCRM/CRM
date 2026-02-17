# Skill: Admin MVC Module Migration

## Context
This skill covers migrating legacy PHP pages to the modern Admin MVC structure with Slim 4 routing.

## File Organization

- **Views**: `src/admin/views/[feature].php` - Use PhpRenderer for clean separation
- **Routes**: `src/admin/routes/[feature].php` - Define route endpoints
- **APIs**: `src/admin/routes/api/[feature-api].php` - Admin API endpoints
- **Services**: `src/ChurchCRM/Service/[Feature]Service.php` - Business logic (shared with APIs)

## Routing Structure

### Admin System Pages (consolidated at `/admin/system/`)

- **Routes**: `src/admin/routes/system.php`
- **Views**: `src/admin/views/`
- **Examples**: `/admin/system/debug`, `/admin/system/backup`
- **Menu entries**: `src/ChurchCRM/Config/Menu/Menu.php`
- **Security**: AdminRoleAuthMiddleware

### Admin APIs (NEW - use this for admin APIs)

- **Location**: `src/admin/routes/api/` (NOT in `src/api/routes/system/`)
- **Example**: `orphaned-files.php` contains `/admin/api/orphaned-files/delete-all` endpoint
- **Routes prefixed**: `/admin/api/` when accessed from frontend
- **Naming**: Use kebab-case for endpoint names (e.g., `/delete-all`)
- **Security**: AdminRoleAuthMiddleware applied at router level

### Finance Module (consolidated at `/finance/`)

- **Entry point**: `src/finance/index.php` with Slim 4 app
- **Routes**: `src/finance/routes/` (dashboard.php, reports.php)
- **Views**: `src/finance/views/`
- **Examples**: `/finance/` (dashboard), `/finance/reports`
- **Security**: FinanceRoleAuthMiddleware (allows admin OR finance permission)
- **Menu entry**: `src/ChurchCRM/Config/Menu/Menu.php` under "Finance"

### Deprecated locations (DO NOT USE)

- ❌ `src/v2/routes/admin/` - REMOVED (admin routes consolidated to `/admin/system/`)
- ❌ `src/api/routes/system/` - Legacy admin APIs (no new files here)

## Key Migration Steps

1. **Extract business logic** from the legacy PHP file into a Service class
2. **Create views** in `src/admin/views/` to render the UI with initial state server-side
3. **Create routes** in `src/admin/routes/` that call the Service and pass data to views
4. **Create APIs** in `src/admin/routes/api/` if UI needs dynamic updates (optional)
5. **Update menu** entries in `src/ChurchCRM/Config/Menu/Menu.php` to point to new route

## Example: User Management Module Migration

### Legacy (mixed concerns)

```
src/UserList.php - Mixed HTML, SQL, business logic, hardcoded settings
```

### Modern (separated concerns)

```
src/ChurchCRM/Service/UserService.php
    - getUserStats() - Statistics + settings config
    - getUserSettingsConfig() - Dynamic settings from SystemConfig

src/admin/views/users.php
    - Dashboard with stats cards
    - User table
    - Settings panels from config

src/admin/routes/api/user-admin.php
    - User operations (reset password, delete, 2FA)
    - All use UserService methods
```

## Route Example

**src/admin/routes/system.php:**

```php
use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->get('/admin/system/users', function (Request $request, Response $response): Response {
    $container = $this->getContainer();
    $userService = $container->get('UserService');
    
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'users.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('User Management'),
        'stats' => $userService->getUserStats(),
        'settings' => $userService->getUserSettingsConfig(),
    ]);
});
```

## View Example

**src/admin/views/users.php:**

```php
<?php
use ChurchCRM\dto\SystemURLs;

// Header
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3><?= $data['stats']['total'] ?></h3>
                <p><?= gettext('Total Users') ?></p>
            </div>
        </div>
    </div>
    <!-- More stats cards -->
</div>

<div class="box">
    <div class="box-header">
        <h3 class="box-title"><?= gettext('Users') ?></h3>
    </div>
    <div class="box-body">
        <!-- User table/content -->
    </div>
</div>

<?php
// Footer
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
```

## API Example

**src/admin/routes/api/user-admin.php:**

```php
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/admin/api/users', function (RouteCollectorProxy $group): void {
    $group->post('/{userId}/reset-password', function (Request $request, Response $response, array $args): Response {
        try {
            $container = $this->getContainer();
            $userService = $container->get('UserService');
            
            $userId = (int)$args['userId'];
            $result = $userService->resetPassword($userId);
            
            return SlimUtils::renderJSON($response, ['data' => $result]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to reset password'), [], 500, $e, $request);
        }
    });
});
```

## SystemConfig for Settings Panels

**Use dynamic settings configuration:**

```php
// In Service
public function getUserSettingsConfig(): array 
{
    $userSettings = [
        'iSessionTimeout',
        'iMaxFailedLogins',
        'bEnableLostPassword'
    ];
    return SystemConfig::getSettingsConfig($userSettings);
}

// In View (example rendering)
<?php foreach ($data['settings'] as $setting): ?>
    <div class="form-group">
        <label><?= $setting['name'] ?></label>
        <input type="<?= $setting['type'] ?>" 
               value="<?= $setting['value'] ?>" 
               name="<?= $setting['category'] ?>" />
    </div>
<?php endforeach; ?>
```

## Middleware Order (CRITICAL)

Slim 4 uses LIFO (Last In, First Out) for middleware:

```php
$app->addBodyParsingMiddleware();       // Parse request body
$app->addRoutingMiddleware();           // Match routes
$app->add(new AdminRoleAuthMiddleware()); // Check admin permission (runs FIRST)
```

## Entry Point Error Handling

For Slim entry points (plugins, admin modules), configure error middleware properly:

```php
// CORRECT - Config-driven error display
$displayErrors = SystemConfig::debugEnabled();
$app->addErrorMiddleware($displayErrors, true, true)
    ->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app): Response {
        $response = $app->getResponseFactory()->createResponse();
        return SlimUtils::renderErrorJSON(
            $response, 
            gettext('An error occurred'), 
            [], 
            500, 
            $exception, 
            $request
        );
    });

// WRONG - Always exposes error details (security risk in production)
$app->addErrorMiddleware(true, true, true);  // ❌ Hardcoded true
```

**Guidelines:**
- **Use `SystemConfig::debugEnabled()`** to control `displayErrorDetails` parameter
- **Set custom error handler** that uses `SlimUtils::renderErrorJSON()` for sanitized responses
- **Never throw HTTP exceptions in API routes** - always catch and return sanitized JSON errors

## Files

**Views:** `src/admin/views/`, `src/finance/views/`
**Routes:** `src/admin/routes/`, `src/finance/routes/`
**APIs:** `src/admin/routes/api/`
**Services:** `src/ChurchCRM/Service/`
**Menu:** `src/ChurchCRM/Config/Menu/Menu.php`
**Middleware:** `src/ChurchCRM/Slim/Middleware/AdminRoleAuthMiddleware.php`
