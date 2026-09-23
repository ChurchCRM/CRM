---
title: Authorization & Security
intent: Role checks and the EditSelf / family-read traps. Read User.php and the middleware.
---

# Authorization

Code: `src/ChurchCRM/model/ChurchCRM/User.php`, `src/ChurchCRM/Slim/Middleware/Request/Auth/`, `AuthMiddleware.php`, `PageInit.php`.

`isAdmin()` short-circuits every `isXxxEnabled()` to true, except EditSelf-exclusive users (those getters return false).

## Entry gate

`hasNoAdminPermissions()` is gone (#9121). Use `isEditSelfExclusive()`: not admin AND EditSelf. That user is confined to `/external/limited-access`.

Zero-permission users (all flags 0) **pass** the gate and get read-only people/family. Read is default (`canReadFamily` / `canReadPerson` always true; keep passing the real IDs — they are reserved ABAC hooks).

Gate must exist in both `PageInit.php` and `AuthMiddleware` (with `isAuthFlowExemptPath()` so login still works).

## Object scope

- Sensitive family routes: `FamilyMiddleware` + `canViewFamily()` (GHSA-jjcj-h3cm-p7x7). Avatar/nav/photo: `FamilyReadMiddleware`, not a constructor flag on `FamilyMiddleware` (Slim would 500).
- `PersonMiddleware` only loads the entity. Put `canEditPerson()` in the handler. Do not add it to `PersonMiddleware` — group-admin member routes share it.
- MVC module: lowest **read** role on the app, write role on POST routes. Menu visibility in `Menu.php` must match the route middleware.

## PR Permission Audit — required before merge

If the diff touches `User.php`, `AuthMiddleware`, `PageInit.php`, `Menu.php`, `FamilyMiddleware`, `PersonMiddleware`, notes/family/person routes, or any `*RoleAuthMiddleware`:

1. EditSelf-exclusive still cannot reach internal pages.
2. Zero-permission user can still read people/family and cannot write.
3. Family profile/geolocation/notes stay behind `canViewFamily()`.
4. Menu items that 403 are a bug; hidden menus in front of open routes are a bug.
5. Do not reintroduce `hasNoAdminPermissions()`.
