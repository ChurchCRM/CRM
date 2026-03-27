---
title: "Groups MVC Guidelines"
intent: "Design and implementation patterns for Groups MVC apps (SundaySchool, Congregation)"
tags: ["groups","mvc","routing","service-layer"]
prereqs: ["routing-architecture.md","service-layer.md","php-best-practices.md"]
complexity: "intermediate"
---

# Groups MVC Guidelines

Purpose: Define structure, routing, services, and permissions for new group-focused MVC apps (`sundaySchool`, `congregation`).

## Actual Directory Structure <!-- learned: 2026-03-15 -->

Sub-module routes and views live **inside** the shared `groups/` module — not in separate sub-directories per feature.

```
src/groups/
├── index.php                          # Slim 4 app — add require for each new route file here
├── routes/
│   ├── dashboard.php                  # /groups/dashboard
│   └── sundayschool.php               # /groups/sundayschool/*
└── views/
    ├── dashboard.php
    └── sundayschool/
        ├── dashboard.php              # rendered by sundayschool.php route
        └── class-view.php
```

**Routes registered in `groups/index.php`:**
```php
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/sundayschool.php';   // ← add new route files here
```

**Route URL pattern:** `/groups/<submodule>/<action>` e.g.:
- `GET /groups/sundayschool/dashboard`
- `GET /groups/sundayschool/class/{id:[0-9]+}`

**PhpRenderer path in route handlers** (from `groups/routes/`):
```php
$renderer = new PhpRenderer(__DIR__ . '/../views/');
return $renderer->render($response, 'sundayschool/dashboard.php', $pageArgs);
```

## Data Flow Pattern

Move all data preparation (service calls, ORM queries, computed aggregates) into the **route handler**, not the view. Pass data to the view via `$pageArgs`:

```php
$pageArgs = [
    'sRootPath'  => SystemURLs::getRootPath(),
    'sPageTitle' => gettext('Sunday School Dashboard'),
    'classStats' => $sundaySchoolService->getClassStats(),
    // ...
];
return $renderer->render($response, 'sundayschool/dashboard.php', $pageArgs);
```

PhpRenderer calls `extract($pageArgs)` so each key becomes a local variable in the view.

## Migration Checklist

When migrating a legacy `src/<module>/LegacyPage.php` to `/groups/<submodule>/<action>`:

1. Create `src/groups/routes/<submodule>.php` — add route closures with data prep logic.
2. Create `src/groups/views/<submodule>/` — add view templates (HTML only, no service calls).
3. Register the new route file in `src/groups/index.php`.
4. Update `src/ChurchCRM/Config/Menu/Menu.php` — change URLs from legacy paths to new `/groups/…` routes.
5. **Delete** the legacy PHP files with `git rm` — do NOT leave redirects or stubs behind.
6. Update the Documentation — update any existing Documentation pages that reference old URLs, or create new Documentation pages if none exist.

## Security & Permissions

- Check `AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()` for mutating operations.
- `ManageGroupRoleAuthMiddleware` is already applied globally in `groups/index.php` — no per-route auth needed for basic access.
- Check `isEmailEnabled()` before rendering email action buttons.

## API Design

- Keep group-scoped API endpoints under `/api/groups/` (existing Slim API app).
- Return standardized JSON via `SlimUtils::renderJSON()` and errors via `SlimUtils::renderErrorJSON()`.

## Functions.php is NOT auto-loaded in PhpRenderer views <!-- learned: 2026-03-15 -->

`groups/index.php` bootstraps via `LoadConfigs.php`, which does **not** include `Functions.php`. `Header.php` also doesn't include it. Any view that calls a helper defined in `Functions.php` (e.g. `generateGroupRoleEmailDropdown()`, `PrintFYIDSelect()`, `change_date_for_place_holder()`) will throw `Call to undefined function`.

Fix: explicitly require it at the top of the view file before `Header.php`:

```php
// Required for helpers like generateGroupRoleEmailDropdown()
require_once SystemURLs::getDocumentRoot() . '/Include/Functions.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
```

## Documentation Update Requirement <!-- learned: 2026-03-15 -->

After migrating any page, update the Documentation:
- Search for any existing page referencing the old URLs (e.g., `sundayschool/SundaySchoolDashboard.php`).
- Update URL references to the new `/groups/…` paths.
- If no Documentation exists for the feature, create a Documentation page under the appropriate section.
References: `routing-architecture.md`, `service-layer.md`, `api-development.md`.
