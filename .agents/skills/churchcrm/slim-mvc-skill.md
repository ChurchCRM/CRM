---
title: "Slim MVCs — Routes, Uses & Security"
intent: "Inventory the Slim apps under src/ and the entity middleware they share"
tags: ["slim","mvc","routing","security"]
prereqs: ["[[routing-architecture]]","[[slim-4-best-practices]]"]
complexity: "intermediate"
---

**Slim MVCs — Skill: Routes, Uses & Security**

Overview
- **Purpose:** Inventory the Slim applications that make up ChurchCRM, say what each one serves, and record the security considerations that apply when adding routes to them. For the conventions *inside* a route file — base paths, `require` mounting, menu registration — see [`routing-architecture.md`](./routing-architecture.md).

Two kinds of Slim app

| Kind | Bootstrap | Prefixes |
|------|-----------|----------|
| **MVC (HTML) modules** | `MvcAppFactory::create('/<prefix>', [...])` | `/admin`, `/event`, `/finance`, `/fundraiser`, `/groups`, `/people`, `/v2` |
| **Bespoke apps** | `AppFactory::create()` + `SlimUtils::getBasePath()` in their own `index.php` | `/api`, `/external`, `/kiosk`, `/plugins`, `/session`, `/setup`&nbsp;† |

&nbsp;† `/setup` is the exception: it calls `AppFactory::create()` like the rest, but computes its
base path by parsing `$_SERVER['SCRIPT_NAME']` (`src/setup/index.php:15-16`) instead of calling
`SlimUtils::getBasePath()`, because that helper depends on `Config.php`, which does not exist
yet while the installer runs. The other five all call `SlimUtils::getBasePath('/<prefix>')`.

`MvcAppFactory` (`src/ChurchCRM/Slim/MvcAppFactory.php:35-62`) supplies the shared stack: base path, body parsing, routing, the HTML error handler, and the middleware chain, whose **execution order** is `AuthMiddleware` → `ChurchInfoRequiredMiddleware` → optional role middleware → `CorsMiddleware` (added in the reverse of that order at `MvcAppFactory.php:54-59`, per Slim 4 LIFO). API-only entry points deliberately do **not** use it — they need a JSON error handler and a different middleware set (`MvcAppFactory.php:17-18`).

Middleware: FamilyMiddleware <!-- learned: 2026-03-03 -->

- **Purpose:** When an API route accepts a `familyId` path parameter, prefer attaching the `FamilyMiddleware` to centralize lookup, validation, and error responses. The middleware loads the `Family` model and attaches it to the request as the `family` attribute so handlers receive a validated object.

Example:

```php
// src/api/routes/map.php:9, :17-21 (trimmed) — attach middleware to the route
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;

$app->group('/map', function (RouteCollectorProxy $group): void {
    $group->get('/neighbors/{familyId:[0-9]+}', 'getMapNeighbors')->add(FamilyMiddleware::class);
});

// handler reads the validated family from the request
function getMapNeighbors(Request $request, Response $response, array $args) {
  /** @var \ChurchCRM\model\ChurchCRM\Family $family */
  $family = $request->getAttribute('family');
  // safe to use $family here — middleware handled validation/404 responses
}
```

`FamilyMiddleware` is one of a family of entity middlewares in `src/ChurchCRM/Slim/Middleware/Api/`, all extending `AbstractEntityMiddleware`: `CalendarMiddleware`, `DepositMiddleware`, `FamilyMiddleware`, `FamilyReadMiddleware`, `GroupMiddleware`, `KioskDeviceMiddleware`, `NoteMiddleware`, `PersonMiddleware`, `PropertyMiddleware`, `PublicCalendarMiddleware`, `UserMiddleware`. A subclass supplies only `getRouteParamName()`, `getAttributeName()` and `loadEntity()`, plus an optional `postEntityLoad()` permission hook — `FamilyMiddleware::postEntityLoad()` is what enforces the family-scope restriction for EditSelf-only users (GHSA-jjcj-h3cm-p7x7). Use `FamilyReadMiddleware` for read-only endpoints that must stay reachable without that scope check.

Current MVC modules (`MvcAppFactory`)

| Prefix | Entry point | Module role gate | Routes / what it serves |
|--------|-------------|------------------|-------------------------|
| `/admin` | `src/admin/index.php` | `AdminRoleAuthMiddleware` | `src/admin/routes/` — `/admin/`, `/admin/get-started`, `/admin/system/*` pages and `/admin/api/*` (system config, logs, upgrade, user admin, database, import/export). Sensitive operations; admin role required. |
| `/event` | `src/event/index.php` | `ViewEventsRoleAuthMiddleware` | `src/event/routes/` — dashboard, calendars, check-in, editor, types, repeat editor, view, audit. Write routes additionally `->add(new AddEventsRoleAuthMiddleware())`. |
| `/finance` | `src/finance/index.php` | `FinanceRoleAuthMiddleware` | `src/finance/routes/` — dashboard, reports, pledges, deposits, funds, plus `routes/api/funds-api.php`. Audit-sensitive. |
| `/fundraiser` | `src/fundraiser/index.php` | `ManageFundraisersRoleAuthMiddleware` | `src/fundraiser/routes/` — fundraiser, donors, donated items, paddle numbers, batch winner, reports. Routes are additionally wrapped in `CSRFMiddleware` and `FundraiserEnabledMiddleware`. |
| `/groups` | `src/groups/index.php` | `ManageGroupRoleAuthMiddleware` | `src/groups/routes/` — dashboard, view, editor, properties form, member role/properties, reports, cart, and `sundayschool.php`. |
| `/people` | `src/people/index.php` | none at module level | `src/people/routes/` — dashboard, list, family, person, view, cart, self-register, map. Per-route permission checks. |
| `/v2` | `src/v2/index.php` | none at module level | `src/v2/routes/` — dashboard/root, search, user, user-current, email, text, cart. Server-rendered initial state for the JS/TS frontend. |

**Sunday School is not its own module.** It ships inside `/groups`: routes in `src/groups/routes/sundayschool.php` (gated by `SundaySchoolEnabledMiddleware`) with views under `src/groups/views/sundayschool/`. There is no `src/groups/congregation/`.

Bespoke Slim apps (not `MvcAppFactory`)

| Prefix | Entry point | Notes |
|--------|-------------|-------|
| `/api` | `src/api/index.php` | REST endpoints used by the frontend and external clients. JSON error handler. Middleware **execution order** is `VersionMiddleware` → `AuthMiddleware` → `CorsMiddleware` — the reverse of the `$app->add()` sequence at `src/api/index.php:29-31`, because Slim 4 is LIFO (last added = first executed). Routes grouped by domain under `src/api/routes/{calendar,finance,people,public,system,users}/`. Keep the API surface backward-compatible. |
| `/external` | `src/external/index.php` | `src/external/routes/` — `calendar.php`, `register.php`, `system.php`, `verify.php`. Often unauthenticated or token-based; validate inputs strictly. |
| `/kiosk` | `src/kiosk/index.php` | `src/kiosk/routes/` — `admin.php`, `device.php`, `api/`. Kiosk device token/cookie flows with a deliberately limited action set. |
| `/plugins` | `src/plugins/index.php` | `/plugins/management/*` and `/plugins/api/*` (admin only), plus `/plugins/{plugin-name}/…` registered by each enabled plugin. Plugin routes run with the plugin's own permission settings — see `plugin-security-scan.md`. |
| `/session` | `src/session/index.php` | Login, two-factor, and `src/session/routes/password-reset.php`. |
| `/setup` | `src/setup/index.php` | Installer. Computes its base path from `SCRIPT_NAME` with no `Config.php` dependency, because it runs before one exists. Sensitive; runs once. |

Notes about how routes are organized
- Routes are grouped by purpose and placed under `src/<area>/routes` (see `src/v2/routes`, `src/api/routes`, `src/admin/routes`, etc.).
- A route file registers on the ambient `$app` and is pulled in with a bare `require` from the module's `index.php`. Route paths are **relative to the module base path** — see [`routing-architecture.md`](./routing-architecture.md) → "Route paths are relative to the module base path".
- Business logic should live in `src/ChurchCRM/Service/` (Service layer) and not in route handlers. Services are instantiated directly (`new PersonService()`) — there is no DI container; see [`service-layer.md`](./service-layer.md).
- Per repository conventions: use Propel ORM Query classes, `SlimUtils::renderErrorJSON()` for API errors, and `RedirectUtils` for page redirects.

Security & operational considerations (general)
- **Auth & permissions:** Many routes rely on role-based checks (admin, finance, edit records). When moving routes between apps, ensure the same middleware and permission checks remain or are migrated. Role middlewares all live in `src/ChurchCRM/Slim/Middleware/Request/Auth/`.
- **Backward compatibility:** External clients and the frontend call `/api` and `/v2` endpoints. Keep those routes stable while refactoring.
- **Locale / i18n:** Wrap new UI strings in `gettext()` / `i18next.t()` and commit only the source change. **Never run `npm run locale:build`** — term extraction and `locale/terms/messages.po` are owned by automation outside this repo (see `i18n-localization.md`).
- **Tests & CI:** Update or add Cypress tests for new endpoints; clear logs before running tests per repo policy.
- **Plugin compatibility:** Plugins register routes and menu items; changing core route namespaces may break them. Provide a compatibility layer or plugin migration notes.

Adding a new MVC module
1. Create `src/<module>/index.php` calling `MvcAppFactory::create('/<module>', [...])` with `dashboardUrl`, `dashboardText` and (if the module needs one) `roleMiddleware`.
2. Add `src/<module>/routes/*.php` — one file per resource — and `require` each from `index.php`.
3. Add `src/<module>/views/*.php` rendered through `PhpRenderer`.
4. Add an `.htaccess` that blocks direct PHP access and routes everything through Slim (copy `src/event/.htaccess`).
5. Register the menu entry in `src/ChurchCRM/Config/Menu/Menu.php`.
6. Put business logic in `src/ChurchCRM/Service/<Feature>Service.php`, not in the route handler.

Full worked detail for each step lives in [`routing-architecture.md`](./routing-architecture.md).

---

Last updated: September 11, 2026
