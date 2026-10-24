---
title: "Groups MVC Guidelines"
intent: "Design and implementation patterns for Groups MVC apps (SundaySchool, Congregation)"
tags: ["groups","mvc","routing","service-layer"]
prereqs: ["[[routing-architecture]]","[[service-layer]]","[[php-best-practices]]"]
complexity: "intermediate"
---

# Groups MVC Guidelines

Structure, routing, services, and permissions for group-focused MVC pages (`sundayschool`, `congregation`).

## Directory Structure

Sub-module routes and views live inside the shared `groups/` module. `src/groups/index.php` is the Slim 4 app and requires each route file.

```
src/groups/
├── index.php
├── routes/<submodule>.php     # /groups/<submodule>/*
└── views/<submodule>/*.php
```

URL pattern: `/groups/<submodule>/<action>`, e.g. `GET /groups/sundayschool/class/{id:[0-9]+}`.

Renderer path from a route file:
```php
$renderer = new PhpRenderer(__DIR__ . '/../views/');
return $renderer->render($response, 'sundayschool/dashboard.php', $pageArgs);
```

## Data Flow

The route handler prepares all data (service calls, ORM queries, aggregates) and passes it to the view through `$pageArgs`. Views hold HTML only. `PhpRenderer` extracts each key into a local variable.

```php
$pageArgs = [
    'sRootPath'  => SystemURLs::getRootPath(),
    'sPageTitle' => gettext('Sunday School Dashboard'),
    'classStats' => $sundaySchoolService->getClassStats(),
];
```

## Moving a Pre-MVC Page onto /groups/

1. Move data logic into a service in `src/ChurchCRM/Service/`.
2. Add `src/groups/routes/<submodule>.php` with route closures that prepare data.
3. Add `src/groups/views/<submodule>/` templates.
4. Require the route file in `src/groups/index.php`.
5. Point `src/ChurchCRM/Config/Menu/Menu.php` and every other caller at the new `/groups/...` URL.
6. `git rm` the old PHP file.
7. Update documentation pages that reference the old URL, or add a page under the matching section.
8. Rename parameters and variables to camelCase.

Import helpers from `ChurchCRM\Utils\*` (e.g. `DateTimeUtils`, `FiscalYearUtils`); `src/Include/Functions.php` does not exist.

## Security & Permissions

- Check `AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()` for mutating operations.
- `ManageGroupRoleAuthMiddleware` is applied to the whole app in `groups/index.php`; routes need no extra auth for basic access.
- Check `isEmailEnabled()` before rendering email action buttons.

## API Design

- Group-scoped API endpoints live under `/api/groups/` (existing Slim API app).
- Return JSON via `SlimUtils::renderJSON()` and errors via `SlimUtils::renderErrorJSON()`.

References: `routing-architecture.md`, `service-layer.md`, `api-development.md`, `admin-mvc-migration.md`.
