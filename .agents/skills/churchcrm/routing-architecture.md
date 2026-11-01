---
title: "Routing & Project Architecture"
intent: "Organization patterns for routes, modules, and menus"
tags: ["routing","architecture","mvc","admin","api"]
prereqs: ["[[slim-4-best-practices]]"]
complexity: "intermediate"
---

# Routing & Project Architecture

How routes, modules, and menus are organized. Inventory of every Slim app: [`slim-mvc-skill.md`](./slim-mvc-skill.md). Middleware ordering: [`slim-4-best-practices.md`](./slim-4-best-practices.md).

## Route files

A route file is a plain PHP script. It registers routes on the ambient `$app` created by the entry point, and `index.php` pulls it in with a bare `require`.

```php
require __DIR__ . '/routes/finance/finance-payments.php';
```

Route paths are relative to the module base path. `MvcAppFactory::create('/finance', ...)` sets the base path, so Slim strips `/finance` before matching: use `'/'` for the module root and `'/reports'` for a sub-page. The same holds for `/admin`, `/event`, `/people`, `/groups`, `/api` and every other module.

`PageHeader::breadcrumbs()` URLs are relative to the site root and include the module prefix (`'/finance/'`).

Services are instantiated directly (`new FinancialService()`); see [`service-layer.md`](./service-layer.md). Middleware classes live under `src/ChurchCRM/Slim/Middleware/` and are added in the entry point or on a route group.

## API routes (`/api/*`)

`src/api/index.php` bootstraps with `AppFactory`. Route files sit under `src/api/routes/<domain>/` (`calendar`, `finance`, `people`, `public`, `system`, `users`, `ministries`); a cross-cutting resource may sit at `src/api/routes/<feature>.php`. Add a `require` line to `src/api/index.php` for each new file.

- Endpoints are kebab-case: `/api/group-members`, `/api/donation-funds`
- Verbs: `GET /resource` list or fetch, `POST /resource` create, `PUT /resource/{id}` update, `DELETE /resource/{id}` delete

```php
$app->group('/payments', function (RouteCollectorProxy $group): void {
    $group->get('/', function (Request $request, Response $response, array $args): Response {
        $financialService = new FinancialService();

        return SlimUtils::renderJSON($response, ['payments' => $financialService->getPayments()]);
    });
});
```

Admin-only operations go in `/admin/api/`. `src/api/routes/system/` holds the non-admin system endpoints.

## Admin pages (`/admin/*`)

`src/admin/index.php` calls `MvcAppFactory::create('/admin', [...])` with `AdminRoleAuthMiddleware` as the module gate. Page routes are in `src/admin/routes/` (system pages in a `$app->group('/system', ...)` in `system.php`), views in `src/admin/views/`, admin JSON endpoints in `src/admin/routes/api/`.

```php
// Served at /admin/get-started
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

- Admin pages return HTML through `PhpRenderer`, with element IDs on test targets
- Render initial state server-side; update dynamically through `/admin/api/*`
- Admin API groups use `$app->group('/api/database', ...)` and a bare `require` in `index.php`; the module middleware already gates them
- Admin API paths are kebab-case with clear verbs: `/admin/api/orphaned-files/delete-all`

## V2 template conventions

V2 pages live in `src/v2/templates/` and render through `Slim\Views\PhpRenderer`. Inline all content in the main template. Every v2 route passes:

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

See `code-standards.md` -> "Admin Page Headers".

## Finance module (`/finance/*`)

`src/finance/index.php`:

```php
$app = MvcAppFactory::create('/finance', [
    'dashboardUrl' => '/finance/',
    'dashboardText' => gettext('Back to Finance Dashboard'),
    'roleMiddleware' => FinanceRoleAuthMiddleware::class,
]);

require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';

$app->run();
```

```php
// src/finance/routes/reports.php: served at /finance/reports
$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        // '' is the group's own root
        ...
    });
});
```

### Role middleware

Role middlewares live in `src/ChurchCRM/Slim/Middleware/Request/Auth/` and extend `BaseAuthRoleMiddleware`, which resolves the user, redirects browsers to `/v2/access-denied`, and returns a JSON 403 to API clients. A subclass supplies three methods:

```php
class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
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

Pass the class to the factory (`'roleMiddleware' => FinanceRoleAuthMiddleware::class`) for module-wide gating. Attach a stricter middleware to a single route with `})->add(new AddEventsRoleAuthMiddleware());`.

## Menu system

Register every admin, finance and module page in `src/ChurchCRM/Config/Menu/Menu.php`. A top-level menu is a `MenuItem` with an empty URI; attach pages with `addSubMenu()`.

`new MenuItem($name, $uri, $hasPermission = true, $icon = '')`:

| Argument | Meaning |
|----------|---------|
| `$name` | Label, wrapped in `gettext()` |
| `$uri` | Path relative to the site root (`'admin/system/users'`); `getURI()` prepends the root path. Empty string = header-only parent |
| `$hasPermission` | `bool`; the item is hidden or disabled when false |
| `$icon` | Font Awesome class, e.g. `'fa-gauge'` |

```php
private static function getAdminMenu(bool $isAdmin): MenuItem
{
    $menu = new MenuItem(gettext('Admin'), '', true, 'fa-screwdriver-wrench');
    $menu->addSubMenu(new MenuItem(gettext('Admin Dashboard'), 'admin/', $isAdmin, 'fa-gauge'));
    $menu->addSubMenu(new MenuItem(gettext('System Users'), 'admin/system/users', $isAdmin, 'fa-user-gear'));

    return $menu;
}
```

Add the builder to the `$menus` array in `Menu::buildMenuItems()`. Badges use `MenuItem::addCounter(new MenuCounter(...))` (see `Menu::getCalendarMenu()`).

### Data-driven entries (one per record)

Groups, Sunday School classes and ministries list records in the menu. The menu is built on every page load, so run one batched query per request that selects ids and names only:

```php
$groups = GroupQuery::create()->filterByType($types)->orderByName()
    ->select(['Id', 'Name'])->find()->toArray();
```

- `MenuRenderer` escapes every label with `InputUtils::escapeHTML()`; pass the raw record name
- `MenuItem::getName()` truncates labels to 22 characters plus `" ..."`; Cypress assertions on long names match the truncated form or use the `href`

### Highlighting an entry for a page outside its URL

`MenuItem::isActive()` compares the request path with the item's URI. When a page belongs to an entry without living under its URL (`/ministries/occurrences/{id}` belongs to the ministry whose schedule generated it), the menu builder resolves the relationship and sets the override:

```php
$item = new MenuItem($ministryName, 'ministries/' . $id, true, 'fa-handshake-angle');
if ($activeMinistryId === $id) {
    $item->setActiveOverride(true);
}
```

Resolve the relationship only on the route that needs it: match `$_SERVER['REQUEST_URI']` first, then query. See `Menu::getVolunteerMinistryIdForCurrentRoute()`.

## Event module (`/event/*`)

`src/event/index.php` uses `MvcAppFactory::create('/event', ['roleMiddleware' => ViewEventsRoleAuthMiddleware::class])`. The module gate is View, so read-only pages stay reachable for users with view-only events permission. Mutating routes add `->add(new AddEventsRoleAuthMiddleware())` for the elevated permission.

### One route file per resource

Split a module's routes by resource:

- One route file per page or feature in `src/<module>/routes/`, with a matching view of the same base name in `src/<module>/views/`
- Register each file with a `require` in the module's `index.php`
- Define small helper functions at the top of the route file; a one-off helper does not need a service class

## Member Portal module (`/portal/*`)

The member-facing area; design in [`member-portal-design.md`](./member-portal-design.md). It is an `MvcAppFactory` module with three distinctions.

| Component | Location |
|-----------|----------|
| Entry point | `src/portal/index.php`, no module role middleware, `'errorHandler' => PortalTwig::createErrorHandler()` |
| Page gate | `ChurchCRM\Portal\Auth\PortalSessionMiddleware`, then `PortalAccessMiddleware` + `CSRFMiddleware`, on the route group. `/api/portal/*` groups use `PortalSessionMiddleware` + `PortalApiMiddleware`. Portal code reads who it serves from `PortalContext`, never `AuthenticationManager::getCurrentUser()` |
| Routes | `src/portal/routes/*.php`: page files use `$group` inside the gated group; `theme-asset.php` and `email-confirm.php` are public and use `$app`; `login.php` is its own public `/login` group |
| Views | Twig: `src/Include/themes/default/templates/**/*.twig` |
| `.htaccess` | `src/portal/.htaccess` blocks `routes/*.php` and routes the rest through `index.php` |

**Landing rule instead of a role gate.** Every login may open the portal, and a member session (no account, signed in at `/portal/login`) passes `AuthMiddleware` only on `/portal`, `/api/portal` and the volunteer API (`/api/ministries/`). An `isEditSelfExclusive()` session is confined to it: `AuthMiddleware::isLimitedAccessAllowedPath()` allows `/portal`, `/api/portal`, the auth-flow pages and `/api/ministries/me/`, and redirects everything else to `/portal/`. `Include/PageInit.php` applies the same rule to PHP pages, and `AuthenticationManager::getDefaultLandingPath()` picks the post-login destination.

**Public routes.** `GET /portal/theme/{name}/{path}` (theme assets), `/portal/login` (member sign-in) and `/portal/email/confirm/{token}` (an email-change link) work without a session. `AuthMiddleware::isPublicPath()` includes them, and the session and access middlewares sit on the page group instead of the app. A public page calls `PortalTwig::preparePage()` itself. A missing asset returns a bare 404.

**Theme templates.** `ChurchCRM\Portal\PortalTwig::render()` turns a portal page into a response. The loader order is active theme, then default theme; `@default/...` addresses core. A render failure shows administrators `errors/theme-error.html.twig` and members `errors/unavailable.html.twig` (both from the default theme) and logs through `LoggerUtils::getAppLogger()` and `error_log()`.

```php
// src/portal/routes/<page>.php: inside the gated group
$group->get('/profile', function (Request $request, Response $response): Response {
    return PortalTwig::render($response, 'profile/index.html.twig', $model, PortalNav::PROFILE);
});
```

The template contract (blocks, partials, globals) is in https://docs.churchcrm.io/administration/member-portal-template-reference. Adding a variable or block is compatible; renaming or removing one is breaking.

`MvcAppFactory` takes `'errorHandler' => callable` for modules whose users must not see the admin shell that the shared Tabler error page renders.

## Volunteer Management (`/ministries`)

The coordinator area is the `src/ministries/` module (`MvcAppFactory::create('/ministries')`) with pages `/ministries/dashboard`, `/ministries/{id}`, `/ministries/occurrences/{id}`. Its API is `/api/ministries/*` (route files in `src/api/routes/ministries/`, each opening `$app->group('/ministries', ...)`; the member surface is `/api/ministries/me/*`). PHP for the feature lives under `src/ChurchCRM/Volunteer/` (`Service/`, `Middleware/`, `Email/`, `VolunteerException`). Webpack bundles use `volunteer-*` names.

## File organization for a new feature

1. **Public API** (`/api/<feature>`): route file under the matching `src/api/routes/<domain>/`, `$app->group('/<feature>', ...)` (the `/api` prefix is the base path), service in `src/ChurchCRM/Service/<Feature>Service.php`, `require` line in `src/api/index.php`.
2. **Admin** (`/admin/system/<feature>`): route in `src/admin/routes/system.php` or its own file, view in `src/admin/views/<feature>.php`, optional API in `src/admin/routes/api/<feature>-api.php`, service in `src/ChurchCRM/Service/`, menu entry in `Menu.php`.
3. **Finance** (`/finance/<feature>`): route in `src/finance/routes/<feature>.php` (path relative to `/finance`), view in `src/finance/views/`, service, `require` line in `src/finance/index.php`, menu entry in `Menu::getDepositsMenu()`.
4. **Converting a PHP page**: extract business logic to a service, create the route and view, update every reference (`grep -r "OldPage.php" src/`), and delete the old file (`admin-mvc-migration.md` -> "No Redirect Shims").
5. **New module directory**: add its own `.htaccess` (copy `src/fundraiser/.htaccess`) so the module's rule sends everything to its `index.php`.

## Testing routes

Cypress:

```javascript
cy.setupAdminSession();
cy.visit('/admin/system/dashboard');
cy.contains('Admin Dashboard').should('exist');

cy.setupStandardSession();
cy.visit('/admin/system/dashboard');
cy.url().should('include', 'access-denied');
```

Related: [`slim-4-best-practices.md`](./slim-4-best-practices.md), [`php-best-practices.md`](./php-best-practices.md), [`frontend-development.md`](./frontend-development.md).
