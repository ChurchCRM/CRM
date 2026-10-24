---
title: "Slim MVCs — Routes, Uses & Security"
intent: "Inventory the Slim apps under src/ and the entity middleware they share"
tags: ["slim","mvc","routing","security"]
prereqs: ["[[routing-architecture]]","[[slim-4-best-practices]]"]
complexity: "intermediate"
---

# Slim MVCs: Routes, Uses & Security

Inventory of the Slim apps under `src/`, what each serves, and the security rules for adding routes. Conventions inside a route file (base paths, `require` mounting, menu registration) live in [`routing-architecture.md`](./routing-architecture.md).

## Two kinds of Slim app

| Kind | Bootstrap | Prefixes |
|------|-----------|----------|
| MVC (HTML) modules | `MvcAppFactory::create('/<prefix>', [...])` | `/admin`, `/event`, `/finance`, `/fundraiser`, `/groups`, `/ministries`, `/people`, `/portal`, `/v2` |
| Bespoke apps | `AppFactory::create()` + `SlimUtils::getBasePath('/<prefix>')` in their own `index.php` | `/api`, `/external`, `/kiosk`, `/plugins`, `/session`, `/setup` |

`/setup` computes its base path from `$_SERVER['SCRIPT_NAME']` because `SlimUtils::getBasePath()` needs `Config.php`, which does not exist while the installer runs.

`MvcAppFactory` (`src/ChurchCRM/Slim/MvcAppFactory.php`) supplies base path, body parsing, routing, the HTML error handler, and the middleware chain. Execution order: `AuthMiddleware` -> `ChurchInfoRequiredMiddleware` -> optional role middleware -> `CorsMiddleware` (Slim 4 is LIFO, so they are added in reverse). API-only entry points use their own JSON error handler and middleware set.

## Entity middleware

When an API route takes an entity id path parameter, attach the matching entity middleware from `src/ChurchCRM/Slim/Middleware/Api/`. It loads the model, returns the 404, and exposes it as a request attribute.

```php
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;

$app->group('/map', function (RouteCollectorProxy $group): void {
    $group->get('/neighbors/{familyId:[0-9]+}', 'getMapNeighbors')->add(FamilyMiddleware::class);
});

function getMapNeighbors(Request $request, Response $response, array $args) {
    /** @var \ChurchCRM\model\ChurchCRM\Family $family */
    $family = $request->getAttribute('family');
}
```

All entity middlewares extend `AbstractEntityMiddleware`. A subclass supplies `getRouteParamName()`, `getAttributeName()` and `loadEntity()`, plus an optional `postEntityLoad()` permission hook. `FamilyMiddleware::postEntityLoad()` enforces the family-scope restriction for EditSelf-only users. Use `FamilyReadMiddleware` for read-only endpoints that stay reachable without that scope check.

## MVC modules

Each module's entry point is `src/<module>/index.php` and its routes live in `src/<module>/routes/`.

| Prefix | Module role gate | Notes |
|--------|------------------|-------|
| `/admin` | `AdminRoleAuthMiddleware` | Staff sign-in at `/admin/` (`StaffSignInPage`), system pages and `/admin/api/*`. Admin role required. |
| `/event` | `ViewEventsRoleAuthMiddleware` | Write routes add `new AddEventsRoleAuthMiddleware()`. |
| `/finance` | `FinanceRoleAuthMiddleware` | Audit-sensitive. `routes/api/` holds the finance JSON endpoints. |
| `/fundraiser` | `ManageFundraisersRoleAuthMiddleware` | Routes sit in a group with `CSRFMiddleware` and `FundraiserEnabledMiddleware`. |
| `/groups` | `ManageGroupRoleAuthMiddleware` | Sunday School is part of this module: `routes/sundayschool.php` behind `SundaySchoolEnabledMiddleware`, views in `views/sundayschool/`. |
| `/ministries` | none at module level; gates per route group | Group carries `CSRFMiddleware` and `VolunteerV2EnabledMiddleware`. |
| `/people` | none at module level | Per-route permission checks. |
| `/portal` | none at module level; `PortalSessionMiddleware` + `PortalAccessMiddleware` gate the page group | Member Portal. Twig-rendered from `Include/themes/`. `GET /portal/theme/{name}/{path}`, `/portal/login` and `/portal/email/confirm/{token}` are public (`AuthMiddleware::isPublicPath()`); everything else needs a portal session (a staff session opening the portal becomes one) and refuses API keys. See [`routing-architecture.md`](./routing-architecture.md) and [`member-portal-design.md`](./member-portal-design.md). |
| `/v2` | none at module level | Server-rendered initial state for the JS/TS frontend. |

## Bespoke apps

| Prefix | Notes |
|--------|-------|
| `/api` | REST endpoints for the frontend and external clients, with a JSON error handler. Middleware executes `VersionMiddleware` -> `AuthMiddleware` -> `CorsMiddleware` (reverse of the `$app->add()` sequence in `src/api/index.php`). Routes are grouped by domain under `src/api/routes/`. Keep the API surface backward-compatible. |
| `/external` | Often unauthenticated or token-based. Validate inputs strictly. |
| `/kiosk` | Kiosk device token and cookie flows with a deliberately limited action set. |
| `/plugins` | `/plugins/management/*` and `/plugins/api/*` are admin only. Each enabled plugin registers `/plugins/{plugin-name}/...` with its own permission settings (see `plugin-security-scan.md`). |
| `/session` | Two-factor, password reset, sign-out (`/session/end`). `/session/begin` redirects to the staff sign-in at `/admin/`. |
| `/setup` | Installer. Sensitive; runs once. |

## Route organization

- Routes are grouped by purpose under `src/<area>/routes`.
- A route file registers on the ambient `$app` and is pulled in with a bare `require` from the module's `index.php`. Route paths are relative to the module base path (see `routing-architecture.md`).
- Business logic lives in `src/ChurchCRM/Service/`. Services are instantiated directly (`new PersonService()`); see [`service-layer.md`](./service-layer.md).
- Use Propel ORM Query classes, `SlimUtils::renderErrorJSON()` for API errors, and `RedirectUtils` for page redirects.

## Security and operations

- When a route moves between apps, carry its middleware and permission checks along. Role middlewares live in `src/ChurchCRM/Slim/Middleware/Request/Auth/`.
- Keep `/api` and `/v2` routes stable; external clients and the frontend call them.
- Wrap new UI strings in `gettext()` / `i18next.t()` and commit only the source change. Term extraction and `locale/terms/messages.po` are owned by automation (see `i18n-localization.md`).
- Add or update Cypress tests for new endpoints.
- Plugins register routes and menu items. A change to core route namespaces needs a plugin compatibility path.

## Adding an MVC module

1. Create `src/<module>/index.php` calling `MvcAppFactory::create('/<module>', [...])` with `dashboardUrl`, `dashboardText` and, if needed, `roleMiddleware`.
2. Add `src/<module>/routes/*.php`, one file per resource, and `require` each from `index.php`.
3. Add `src/<module>/views/*.php` rendered through `PhpRenderer`.
4. Add an `.htaccess` that routes everything through Slim (copy `src/event/.htaccess`).
5. Register the menu entry in `src/ChurchCRM/Config/Menu/Menu.php`.
6. Put business logic in `src/ChurchCRM/Service/<Feature>Service.php`.

Details for each step: [`routing-architecture.md`](./routing-architecture.md).
