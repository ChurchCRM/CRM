---
title: "Routing & Project Architecture"
intent: "Organization patterns for routes, modules, and menus"
tags: ["routing","architecture","mvc","admin","api"]
prereqs: ["slim-4-best-practices.md"]
complexity: "intermediate"
---

# Routing & Project Architecture

Guide to organizing code in ChurchCRM across API routes, admin pages, and the finance module.

---

## Overview

ChurchCRM uses a consolidated routing structure with three main entry points:

| Module | Entry Point | Purpose |
|--------|------------|---------|
| **API** | `src/api/index.php` | REST API endpoints (`/api/*`) |
| **Admin** | `src/admin/routes/` | System administration pages (`/admin/system/*` & `/admin/api/*`) |
| **Finance** | `src/finance/index.php` | Financial module (`/finance/*`) |
| **Legacy** | `src/*.php` | Traditional PHP pages (older codebase) |

---

## API Routes (`/api/*`)

### Location & Structure

```
src/api/
├── index.php              # Slim 4 app initialization
├── middleware/
│   ├── AuthMiddleware.php
│   └── VersionMiddleware.php
└── routes/
    ├── families.php
    ├── payments.php
    ├── events.php
    └── [feature].php      # One file per API resource
```

### Patterns

**Endpoint Naming:**
- Use kebab-case for all endpoints: `/api/group-members`, `/api/donation-funds`
- Use HTTP verbs correctly:
  - `GET /resource` - List or fetch
  - `POST /resource` - Create new
  - `PUT /resource/{id}` - Update
  - `DELETE /resource/{id}` - Delete

**Route Definition:**
```php
// src/api/routes/payments.php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        // Query service for list
        return SlimUtils::renderJSON($response, ['data' => $payments]);
    });

    $group->post('', function (Request $request, Response $response): Response {
        // Create new payment via service
        return SlimUtils::renderJSON($response, ['data' => $newPayment], 201);
    });

    $group->put('/{id}', function (Request $request, Response $response, array $args): Response {
        // Update payment
        return SlimUtils::renderJSON($response, ['data' => $updated]);
    });
};
```

**Mounting Routes in index.php:**
```php
// src/api/index.php
$app->group('/api/payments', require __DIR__ . '/routes/payments.php');
$app->group('/api/families', require __DIR__ . '/routes/families.php');
$app->group('/api/events', require __DIR__ . '/routes/events.php');
```

---

## Admin Pages (`/admin/system/*`)

### Location & Structure

```
src/admin/
├── routes/
│   ├── system.php          # Admin system page routes
│   └── api/
│       ├── database.php    # Database admin APIs
│       ├── users.php       # User management APIs
│       └── [feature-api].php
├── views/
│   ├── dashboard.php       # AdminLTE dashboard page
│   ├── settings.php        # Settings/configuration panel
│   ├── users.php           # User list/management
│   ├── backup.php          # Backup & restore
│   └── [feature].php
└── middleware/
    └── AdminRoleAuthMiddleware.php
```

### Patterns

**Admin Page Routes (system.php):**
```php
// src/admin/routes/system.php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

return function ($app): void {
    // Dashboard
    $app->get('/system/dashboard', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $data = $container->get('AdminService')->getDashboardData();
        
        return $renderer->render($response, 'dashboard.php', [
            'sPageTitle' => gettext('Admin Dashboard'),
            'sRootPath' => SystemURLs::getRootPath(),
            'data' => $data
        ]);
    })->setName('admin.dashboard');

    // Settings page
    $app->get('/system/settings', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $settingsConfig = SystemConfig::getSettingsConfig(['iSessionTimeout', 'iMaxFailedLogins']);
        
        return $renderer->render($response, 'settings.php', [
            'sPageTitle' => gettext('System Settings'),
            'sRootPath' => SystemURLs::getRootPath(),
            'settings' => $settingsConfig
        ]);
    })->setName('admin.settings');
};
```

**Admin Page Requirements:**
- Routes return HTML (use PhpRenderer)
- ALL pages must have element IDs for test selectors
- Initial state rendered server-side (PHP)
- Dynamic updates via `/admin/api/*` endpoints

### Admin APIs (`/admin/api/*`)

API endpoints for admin operations. Accessible ONLY to admin-role users.

**Location & Patterns:**
```php
// src/admin/routes/api/database.php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group): void {
    $group->post('/backup', function (Request $request, Response $response) {
        // Call admin service for backup operation
        $result = $container->get('BackupService')->createBackup();
        return SlimUtils::renderJSON($response, ['data' => $result]);
    });

    $group->delete('/reset', function (Request $request, Response $response) {
        // Reset database (dangerous operation)
        $confirmed = $request->getParsedBody()['confirmed'] ?? false;
        if (!$confirmed) {
            return SlimUtils::renderErrorJSON($response, gettext('Confirmation required'), [], 400);
        }
        
        $result = $container->get('DatabaseService')->resetDatabase();
        return SlimUtils::renderJSON($response, ['data' => $result]);
    });
};
```

**Admin API Mounting (in admin index.php):**
```php
$app->group('/admin/api/database', require __DIR__ . '/routes/api/database.php')
    ->add(new AdminRoleAuthMiddleware());
    
$app->group('/admin/api/users', require __DIR__ . '/routes/api/users.php')
    ->add(new AdminRoleAuthMiddleware());
```

**Nameing Convention:**
- Use kebab-case: `/admin/api/orphaned-files/delete-all`
- Group logical operations: `/admin/api/users/*`, `/admin/api/database/*`
- Clear action verbs: `delete-all`, `reset`, `export`

---

## Finance Module (`/finance/*`)

### Location & Structure

```
src/finance/
├── index.php              # Slim 4 app initialization
├── routes/
│   ├── dashboard.php
│   ├── reports.php
│   └── [feature].php
├── views/
│   ├── dashboard.php      # Finance dashboard
│   ├── reports.php        # Reporting page
│   └── [feature].php
└── middleware/
    └── FinanceRoleAuthMiddleware.php
```

### Patterns

**Entry Point (index.php):**
```php
<?php
// src/finance/index.php
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

$app = AppFactory::create();
$container = $app->getContainer();

// Register services
$container->set('FinanceService', new FinanceService());

// Add middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new FinanceRoleAuthMiddleware());

// Load routes
$app->group('', require __DIR__ . '/routes/dashboard.php');
$app->group('', require __DIR__ . '/routes/reports.php');

$app->run();
```

**Finance Page Routes:**
```php
// src/finance/routes/dashboard.php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

return function ($app): void {
    $app->get('/finance/', function (Request $request, Response $response) use ($app) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $financeService = $app->getContainer()->get('FinanceService');
        
        return $renderer->render($response, 'dashboard.php', [
            'sPageTitle' => gettext('Finance Dashboard'),
            'sRootPath' => SystemURLs::getRootPath(),
            'summary' => $financeService->getDashboardSummary(),
            'recentTransactions' => $financeService->getRecentTransactions(10)
        ]);
    })->setName('finance.dashboard');

    $app->get('/finance/reports', function (Request $request, Response $response) use ($app) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $financeService = $app->getContainer()->get('FinanceService');
        
        return $renderer->render($response, 'reports.php', [
            'sPageTitle' => gettext('Financial Reports'),
            'sRootPath' => SystemURLs::getRootPath(),
            'reports' => $financeService->getAvailableReports()
        ]);
    })->setName('finance.reports');
};
```

**Finance Authorization:**
```php
// src/finance/middleware/FinanceRoleAuthMiddleware.php
<?php
class FinanceRoleAuthMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
    {
        $user = AuthenticationManager::getCurrentUser();
        
        // Allow admin OR users with finance permission
        if (!$user->isAdmin() && !$user->isFinanceEnabled()) {
            RedirectUtils::securityRedirect('FinanceAccess');
        }
        
        return $handler->handle($request);
    }
}
```

---

## Menu System Integration

### Registering Pages in Menu

All admin/finance pages must be registered in the main menu system.

**Location:**
```php
// src/ChurchCRM/Config/Menu/Menu.php
```

**Pattern:**
```php
// Admin System Pages
$adminMenu = new MenuSection('Admin', 'fa-cog');
$adminMenu->addItem(
    new MenuItem(
        gettext('Dashboard'),
        'admin/routes/system.php',
        'admin.dashboard',
        'fa-chart-line'
    )
);
$adminMenu->addItem(
    new MenuItem(
        gettext('System Settings'),
        'admin/routes/system.php',
        'admin.settings',
        'fa-sliders-h'
    )
);

// Finance Module
$financeMenu = new MenuSection(gettext('Finance'), 'fa-dollar-sign');
$financeMenu->addItem(
    new MenuItem(
        gettext('Dashboard'),
        'finance/routes/dashboard.php',
        'finance.dashboard',
        'fa-chart-pie'
    )
);
$financeMenu->addItem(
    new MenuItem(
        gettext('Reports'),
        'finance/routes/reports.php',
        'finance.reports',
        'fa-file-pdf'
    )
);
```

---

## Deprecated Locations (DO NOT USE)

| Path | Status | Reason |
|------|--------|--------|
| `src/v2/routes/admin/` | REMOVED | Admin routes consolidated to `/admin/` |
| `src/api/routes/system/` | LEGACY | Use `/admin/api/` for admin operations |
| `src/ChurchCRM/Admin/` | LEGACY | Use `/admin/` structure instead |

**Migration Path:**
- Consolidate admin pages to `/admin/system/`
- Move admin APIs to `/admin/api/`
- Update menu registrations in `Menu.php`
- Redirect legacy URLs to new locations

---

## File Organization Principles

### When Creating a New Feature

1. **If it's a public API** (`/api/endpoint`):
   - Create route file in `src/api/routes/[feature].php`
   - Create service in `src/ChurchCRM/Service/[Feature]Service.php`
   - Mount in `src/api/index.php`

2. **If it's admin functionality** (`/admin/system/feature`):
   - Create route in `src/admin/routes/system.php` (or separate file)
   - Create view in `src/admin/views/[feature].php`
   - Create API in `src/admin/routes/api/[feature]-api.php` if needed dynamics
   - Create service in `src/ChurchCRM/Service/[Feature]Service.php`
   - Register in `src/ChurchCRM/Config/Menu/Menu.php`

3. **If it's finance-related** (`/finance/feature`):
   - Create route in `src/finance/routes/[feature].php`
   - Create view in `src/finance/views/[feature].php`
   - Create service in `src/ChurchCRM/Service/Finance/[Feature]Service.php`
   - Register in `Menu.php` under Finance section

4. **If it's a legacy page** (`src/Page.php`):
   - Gradually migrate to one of the above
   - Start by extracting business logic to Service class
   - Create new route structure and views
   - Redirect legacy page to new location

---

## Testing Routes

### Unit Tests
```php
// Test admin auth middleware
$user = new User();
$user->setRole('user'); // Not admin

$middleware = new AdminRoleAuthMiddleware();
$response = $middleware->process($request, $handler);

// Should redirect to access-denied
$this->assertEquals(302, $response->getStatusCode());
```

### Integration Tests (Cypress)
```javascript
// Test admin page access
cy.setupAdminSession();
cy.visit('/admin/system/dashboard');
cy.contains('Admin Dashboard').should('exist');

// Test standard user gets redirected
cy.setupStandardSession();
cy.visit('/admin/system/dashboard');
cy.url().should('include', 'access-denied');
```

---

**Related Skills:**
- [Slim 4 Best Practices](./slim-4-best-practices.md) - Routing foundation
- [PHP Best Practices](./php-best-practices.md) - Service layer patterns
- [Bootstrap & AdminLTE](./bootstrap-adminlte.md) - Admin page UI

---

Last updated: February 16, 2026
