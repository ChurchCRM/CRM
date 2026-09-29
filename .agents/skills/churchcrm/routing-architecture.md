---
title: "Routing & Project Architecture"
intent: "Organization patterns for routes, modules, and menus"
tags: ["routing","architecture","mvc","admin","api"]
prereqs: ["[[slim-4-best-practices]]"]
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
| **Admin** | `src/admin/index.php` | System administration pages (`/admin/system/*` & `/admin/api/*`) |
| **Finance** | `src/finance/index.php` | Financial module (`/finance/*`) |
| **Event** | `src/event/index.php` | Events module (`/event/*`) |
| **Legacy** | `src/*.php` | Traditional PHP pages (older codebase) |

---

## API Routes (`/api/*`)

### Location & Structure

```
src/api/
├── index.php              # Slim 4 app bootstrap (AppFactory — NOT MvcAppFactory)
└── routes/
    ├── calendar/          # events.php, calendar.php
    ├── finance/           # finance-payments.php, finance-deposits.php, …
    ├── people/            # people-person.php, people-family.php, notes.php, …
    ├── public/            # public.php, public-register.php, public-calendar.php, …
    ├── system/            # system-issues.php, property-types.php, …
    ├── users/             # user.php, user-current.php, user-settings.php
    ├── cart.php           # single-resource files sit at the top level
    ├── search.php
    └── map.php
```

There is **no `src/api/middleware/`**. Every middleware class lives under
`src/ChurchCRM/Slim/Middleware/` and is added in the entry point
(`src/api/index.php:29-31` adds `CorsMiddleware`, `AuthMiddleware`, `VersionMiddleware`).

### Patterns

**Endpoint Naming:**
- Use kebab-case for all endpoints: `/api/group-members`, `/api/donation-funds`
- Use HTTP verbs correctly:
  - `GET /resource` - List or fetch
  - `POST /resource` - Create new
  - `PUT /resource/{id}` - Update
  - `DELETE /resource/{id}` - Delete

**Route Definition:**

A route file is a plain PHP script. It **does not return a closure** — it registers routes
on the ambient `$app` that the entry point created, then `index.php` simply `require`s it.
Paths inside the file are **relative to the app base path** (`/api`), so the group below is
served at `/api/payments`.

```php
// src/api/routes/finance/finance-payments.php:1-37 (trimmed)
<?php

use ChurchCRM\Service\FinancialService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/payments', function (RouteCollectorProxy $group): void {
    $group->get('/', function (Request $request, Response $response, array $args): Response {
        $financialService = new FinancialService();

        return SlimUtils::renderJSON(
            $response,
            ['payments' => $financialService->getPayments()]
        );
    });
});
```

Services are **instantiated directly** (`new FinancialService()`). ChurchCRM has no DI
container — see [`service-layer.md`](./service-layer.md).

**Mounting Routes in index.php:**

Each route file is a bare `require`. Never `$app->group('/x', require …)` — the file
returns nothing, so that form registers an empty group.

```php
// src/api/index.php:38-73 (trimmed)
require __DIR__ . '/routes/calendar/events.php';
require __DIR__ . '/routes/finance/finance-payments.php';
require __DIR__ . '/routes/people/people-person.php';
require __DIR__ . '/routes/users/user.php';

$app->run();
```

---

## Admin Pages (`/admin/system/*`)

### Location & Structure

```
src/admin/
├── index.php               # MvcAppFactory::create('/admin', [...])
├── routes/
│   ├── dashboard.php       # /admin/ and /admin/get-started
│   ├── system.php          # $app->group('/system', …) — admin system pages
│   ├── import.php
│   ├── export.php
│   ├── get-started.php
│   └── api/
│       ├── database.php    # $app->group('/api/database', …)
│       ├── user-admin.php  # User management APIs
│       ├── orphaned-files.php
│       ├── options.php
│       ├── upgrade.php
│       └── system/
│           ├── system-config.php
│           └── system-logs.php
└── views/
    ├── dashboard.php       # Tabler dashboard page
    ├── users.php           # User list/management
    └── [feature].php
```

There is **no `src/admin/middleware/`**. `AdminRoleAuthMiddleware` lives at
`src/ChurchCRM/Slim/Middleware/Request/Auth/AdminRoleAuthMiddleware.php` and is applied to
the whole module by the factory (`src/admin/index.php:8-12`).

### Patterns

**Admin Page Routes:**

```php
// src/admin/routes/dashboard.php:1-43 (trimmed)
<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Served at /admin/get-started — the '/admin' prefix comes from MvcAppFactory::create()
$app->get('/get-started', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Get Your Data Into ChurchCRM'),
        'sPageSubtitle' => gettext('Choose how you would like to populate your database.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Get Started')],
        ]),
    ];

    return $renderer->render($response, 'get-started.php', $pageArgs);
});
```

Pages that share a URL segment are wrapped in a group instead — `src/admin/routes/system.php:32`
opens `$app->group('/system', function (RouteCollectorProxy $group): void {`, so every route
inside it is served under `/admin/system/*`.

**Admin Page Requirements:**
- Routes return HTML (use PhpRenderer)
- ALL pages must have element IDs for test selectors
- Initial state rendered server-side (PHP)
- Dynamic updates via `/admin/api/*` endpoints

### Admin APIs (`/admin/api/*`)

API endpoints for admin operations. Accessible ONLY to admin-role users.

**Location & Patterns:**
```php
// src/admin/routes/api/database.php:1-60 (trimmed)
<?php

use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/database/* — '/admin' is the app base path, so the
// group path must NOT repeat it.
$app->group('/api/database', function (RouteCollectorProxy $group): void {
    $group->get('/people/export/chmeetings', 'exportChMeetings');

    $group->post('/backup', function (Request $request, Response $response, array $args): Response {
        $input = $request->getParsedBody();
        // … run the backup job …

        return SlimUtils::renderJSON($response, ['data' => $result]);
    });
});
```

**Admin API Mounting (in `src/admin/index.php`):**

A bare `require`, with no per-mount middleware — `AdminRoleAuthMiddleware` already gates
every route in the `/admin` app.

```php
// src/admin/index.php:17-23
require __DIR__ . '/routes/api/demo.php';
require __DIR__ . '/routes/api/database.php';
require __DIR__ . '/routes/api/orphaned-files.php';
require __DIR__ . '/routes/api/system/system-config.php';
require __DIR__ . '/routes/api/system/system-logs.php';
```

**Naming Convention:**
- Use kebab-case: `/admin/api/orphaned-files/delete-all`
- Group logical operations: `/admin/api/users/*`, `/admin/api/database/*`
- Clear action verbs: `delete-all`, `reset`, `export`

---

## V2 Template Conventions <!-- learned: 2026-03-25 -->

V2 pages live in `src/v2/templates/` and are rendered by `Slim\Views\PhpRenderer`. Inside a template, `$this` is the PhpRenderer instance.

### Do NOT use `$this->fetch()` for sub-templates

`PhpRenderer::fetch()` can render sub-templates, but **do not use this pattern**. Inline all content directly in the main template file. Sub-template splitting adds indirection without benefit — every other v2 template inlines its content, and the cart page was the last holdout (fixed 2026-03-25).

### Required page variables

Every v2 route **must** pass these to the renderer:

```php
$pageArgs = [
    'sRootPath'     => SystemURLs::getRootPath(),
    'sPageTitle'    => gettext('Page Title'),
    'sPageSubtitle' => gettext('Short description'),
    'aBreadcrumbs'  => PageHeader::breadcrumbs([
        [gettext('Parent'), '/parent/path'],
        [gettext('Current Page')],
    ]),
];
```

Omitting `aBreadcrumbs` or `sPageSubtitle` results in a page with no navigation context. See `tabler-components.md` → "Unified Page Header" for full reference.

---

## Finance Module (`/finance/*`)

### Location & Structure

```
src/finance/
├── index.php              # MvcAppFactory::create('/finance', [...])
├── routes/
│   ├── dashboard.php      # $app->get('/', …)        → /finance/
│   ├── reports.php        # $app->group('/reports')   → /finance/reports/*
│   ├── pledges.php        # $app->group('/pledge')
│   ├── deposits.php       # $app->group('/deposit')
│   ├── fund.php           # $app->group('/fund')
│   ├── funds.php
│   └── api/
│       └── funds-api.php  # $app->group('/api/funds')
└── views/
    ├── dashboard.php      # Finance dashboard
    ├── reports.php        # Reporting page
    └── [feature].php
```

There is **no `src/finance/middleware/`**. `FinanceRoleAuthMiddleware` lives at
`src/ChurchCRM/Slim/Middleware/Request/Auth/FinanceRoleAuthMiddleware.php` alongside every
other role middleware.

### Patterns

**Entry Point (index.php):**

All MVC modules now use `MvcAppFactory` — see [`slim-4-best-practices.md`](./slim-4-best-practices.md) for middleware ordering rules.

```php
// src/finance/index.php:1-23 (trimmed)
<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app = MvcAppFactory::create('/finance', [
    'dashboardUrl' => '/finance/',
    'dashboardText' => gettext('Back to Finance Dashboard'),
    'roleMiddleware' => FinanceRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/deposits.php';

$app->run();
```

**Finance Page Routes:**

> ⚠️ **Route paths are relative to the module base path — never repeat the prefix.**
> `MvcAppFactory::create('/finance', …)` calls `$app->setBasePath(SlimUtils::getBasePath('/finance'))`
> (`src/ChurchCRM/Slim/MvcAppFactory.php:42`), so Slim strips `/finance` before matching.
> Writing `$app->get('/finance/reports', …)` inside a finance route file therefore serves
> **`/finance/finance/reports`** — a URL nothing links to. Use `'/'` for the module root and
> `'/reports'` for a sub-page. The same applies to `/admin`, `/event`, `/people`, `/groups`
> and every other `MvcAppFactory` module.

```php
// src/finance/routes/dashboard.php:1-33 (trimmed) — served at /finance/
<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Match /finance root path - Finance Dashboard
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Finance Dashboard'),
        'sPageSubtitle' => gettext('Manage donations, pledges, and financial records'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance')],
        ]),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
```

```php
// src/finance/routes/reports.php:11-28 (trimmed) — served at /finance/reports
$app->group('/reports', function (RouteCollectorProxy $group): void {

    // Financial Reports selection page (migrated from FinancialReports.php)
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Reports')],
            ]),
        ];

        return $renderer->render($response, 'reports.php', $pageArgs);
    });
});
```

Note the empty string in `$group->get('', …)` — that is the group's own root
(`/finance/reports`). Breadcrumb URLs, by contrast, are **site-root relative** and *do*
include the module prefix (`'/finance/'`), because `PageHeader::breadcrumbs()` prepends
`getRootPath()`, not the app base path.

**Finance Authorization:**

Role middlewares extend `BaseAuthRoleMiddleware`
(`src/ChurchCRM/Slim/Middleware/Request/Auth/BaseAuthRoleMiddleware.php`), which implements
`process()` once — resolving the current user, redirecting browser requests to
`/v2/access-denied` and returning a JSON 403 to API clients. A subclass only supplies three
small methods:

```php
// src/ChurchCRM/Slim/Middleware/Request/Auth/FinanceRoleAuthMiddleware.php
<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        // Admins have access to all finance features, plus users with finance permission
        return $this->user->isAdmin() || $this->user->isFinanceEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be an Admin or have Finance permission');
    }

    protected function getRoleName(): string
    {
        return 'Finance';
    }
}
```

Pass the class-string to the factory for module-wide gating
(`'roleMiddleware' => FinanceRoleAuthMiddleware::class`), or attach an extra middleware to a
single route for a higher permission — e.g. `src/event/routes/editor.php:57`:
`})->add(new AddEventsRoleAuthMiddleware());`

---

## Menu System Integration

### Registering Pages in Menu

All admin/finance pages must be registered in the main menu system.

**Location:**
```php
// src/ChurchCRM/Config/Menu/Menu.php
```

**Pattern:**

There is **no `MenuSection` class** — `src/ChurchCRM/Config/Menu/` holds only `Menu.php`,
`MenuItem.php` and `MenuCounter.php`. A top-level menu *is* a `MenuItem` with an empty URI;
its pages are attached with `MenuItem::addSubMenu()`.

The constructor is `__construct($name, $uri, $hasPermission = true, $icon = '')`
(`src/ChurchCRM/Config/Menu/MenuItem.php:18`):

| Argument | Meaning |
|----------|---------|
| `$name` | Label, wrapped in `gettext()` |
| `$uri` | **URL path relative to the site root** — `MenuItem::getURI()` prepends `SystemURLs::getRootPath()`. Use `'admin/system/users'`, never a source file path. Empty string = a header-only parent. |
| `$hasPermission` | `bool` — the item is rendered disabled/hidden when false |
| `$icon` | Font Awesome class, e.g. `'fa-gauge'` |

```php
// src/ChurchCRM/Config/Menu/Menu.php:364-377 (trimmed)
private static function getAdminMenu(bool $isAdmin): MenuItem
{
    $menu = new MenuItem(gettext('Admin'), '', true, 'fa-screwdriver-wrench');
    $menu->addSubMenu(new MenuItem(gettext('Admin Dashboard'), 'admin/', $isAdmin, 'fa-gauge'));
    $menu->addSubMenu(new MenuItem(gettext('System Users'), 'admin/system/users', $isAdmin, 'fa-user-gear'));
    $menu->addSubMenu(new MenuItem(gettext('Export'), 'admin/export', $isAdmin, 'fa-file-export'));

    return $menu;
}
```

Register the builder in the `$menus` array in `Menu::buildMenuItems()`
(`src/ChurchCRM/Config/Menu/Menu.php:38-49`):

```php
$menus = [
    'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-gauge'),
    'Calendar'     => self::getCalendarMenu($canViewEvents),
    'People'       => self::getPeopleMenu($isAdmin, $isMenuOptions, $currentUser->isAddRecordsEnabled()),
    'Deposits'     => self::getDepositsMenu($isAdmin, $currentUser->isFinanceEnabled()),
    // …
];
```

Badges are added with `MenuItem::addCounter(new MenuCounter(...))` — see
`Menu::getCalendarMenu()` (`src/ChurchCRM/Config/Menu/Menu.php:82-93`).

---

## Event MVC Module (`/event/*`) <!-- learned: 2026-04-07, updated 2026-04-09 -->

The `/event` MVC module follows the same pattern as `/admin` and `/finance`:

| Component | Location |
|-----------|----------|
| Entry point | `src/event/index.php` using `MvcAppFactory::create('/event', [...])` |
| Module-level middleware | `ViewEventsRoleAuthMiddleware` (gates the entire `/event/*` namespace) |
| Per-route write middleware | `AddEventsRoleAuthMiddleware` added explicitly to mutating routes |
| Routes | `src/event/routes/*.php` (one file per resource) |
| Views | `src/event/views/*.php` rendered via PhpRenderer |
| `.htaccess` | Blocks direct PHP access and routes through Slim |

**Why split View vs Add at the middleware level**: Read-only pages in the module (`/event/dashboard`, `/event/calendars`, `/event/checkin`, `/event/view/{id}`) must be reachable by users with View-only events permission. Write routes (`POST /event/editor`, `POST /event/types/*`, `POST /event/repeat-editor`) explicitly add `->add(new AddEventsRoleAuthMiddleware())` for the elevated permission. Putting `AddEventsRoleAuthMiddleware` at the module level was the original mistake — it 403'd menu items for view-only users.

```
src/event/
├── index.php              # MvcAppFactory::create('/event', [
│                          #     'roleMiddleware' => ViewEventsRoleAuthMiddleware::class
│                          # ])
├── .htaccess              # Blocks direct PHP, routes through Slim
├── routes/
│   ├── event.php          # /event/cart-to-event
│   ├── checkin.php        # /event/checkin[/{eventId}]
│   ├── repeat-editor.php  # /event/repeat-editor[/{typeId}]
│   ├── calendar.php       # /event/calendars
│   ├── list-events.php    # /event/dashboard
│   ├── types.php          # /event/types[/{id}]
│   ├── editor.php         # /event/editor[/{id}]
│   ├── view.php           # /event/view/{id}
│   └── audit.php          # /event/audit
└── views/
    └── [feature].php      # Tabler-rendered views
```

### Route File Naming & Organization — One File per Resource <!-- learned: 2026-04-08 -->

For modules with many legacy pages (the `/event` migration had ~10), split routes by
logical resource rather than cramming everything into one `event.php`:

- One route file per page/feature in `src/event/routes/` (e.g., `event-types.php`,
  `event-attendance.php`, `event-checkin.php`)
- Matching view file of the same base name in `src/event/views/`
- Register each route file in `src/event/index.php`:
  ```php
  require __DIR__ . '/routes/event-types.php';
  require __DIR__ . '/routes/event-attendance.php';
  require __DIR__ . '/routes/event-checkin.php';
  ```
- Small per-route helper functions can be defined at the top of the route file — do
  not create a dedicated service class for one-off helpers that only serve that page.

This keeps each file under ~300 lines and makes it obvious which route handles which view.

---

## Deprecated Locations (DO NOT USE)

| Path | Status | Reason |
|------|--------|--------|
| `src/v2/routes/admin/` | REMOVED | Admin routes consolidated to `/admin/` |
| `src/api/routes/system/` (admin-only endpoints) | LEGACY | Admin-only operations belong in `/admin/api/`. The directory itself is current — it still hosts non-admin system endpoints (`system-issues.php`, `property-types.php`, `telemetry-consent.php`). |
| `src/ChurchCRM/Admin/` | LEGACY | Use `/admin/` structure instead |

**Migration Path:**
- Consolidate admin pages to `/admin/system/`
- Move admin APIs to `/admin/api/`
- Update menu registrations in `Menu.php`
- **Update all callers** to use the new URL, then **delete** the legacy file — never redirect from the old location (see `admin-mvc-migration.md` → "No Redirect Shims")

---

## File Organization Principles

### When Creating a New Feature

1. **If it's a public API** (`/api/endpoint`):
   - Create the route file under the matching domain directory —
     `src/api/routes/{calendar,finance,people,public,system,users}/[feature].php`
     (a genuinely cross-cutting resource may sit at `src/api/routes/[feature].php`)
   - Register routes on the ambient `$app` with `$app->group('/[feature]', …)` — the
     `/api` prefix is already the base path
   - Create service in `src/ChurchCRM/Service/[Feature]Service.php`
   - Add a `require __DIR__ . '/routes/…';` line to `src/api/index.php`

2. **If it's admin functionality** (`/admin/system/feature`):
   - Create route in `src/admin/routes/system.php` (or separate file)
   - Create view in `src/admin/views/[feature].php`
   - Create API in `src/admin/routes/api/[feature]-api.php` if needed dynamics
   - Create service in `src/ChurchCRM/Service/[Feature]Service.php`
   - Register in `src/ChurchCRM/Config/Menu/Menu.php`

3. **If it's finance-related** (`/finance/feature`):
   - Create route in `src/finance/routes/[feature].php` — path **relative** to `/finance`
   - Create view in `src/finance/views/[feature].php`
   - Create service in `src/ChurchCRM/Service/[Feature]Service.php`
   - Add a `require` line to `src/finance/index.php`
   - Register the menu entry in `Menu::getDepositsMenu()` (the Finance menu)

4. **If it's a legacy page** (`src/Page.php`):
   - Gradually migrate to one of the above
   - Start by extracting business logic to Service class
   - Create new route structure and views
   - **Search for every link/reference to the legacy file** (use `grep -r "LegacyPage.php" src/`) and update them to the new route
   - **Delete the legacy file** — do NOT leave it as a redirect shim (see `admin-mvc-migration.md` → "No Redirect Shims")

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
- [Tabler Components](./tabler-components.md) - Admin page UI

---

Last updated: September 11, 2026
