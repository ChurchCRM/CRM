---
title: Authorization & Security
intent: Role checks and EditSelf / family-read traps. Read User.php and the middleware for APIs.
---

# Authorization

Code: `src/ChurchCRM/model/ChurchCRM/User.php`, `AuthMiddleware.php`, `PageInit.php`, `src/ChurchCRM/Slim/Middleware/Request/Auth/`.

`isAdmin()` makes every `isXxxEnabled()` true, except EditSelf-exclusive users (those getters return false).

`isEditSelfExclusive()` means not admin AND EditSelf.

## Who gets in

| User | Entry gate | Effect |
|------|------------|--------|
| Admin | pass | full |
| Any module flag (Notes, Finance, …) | pass | read all; write per flag |
| All flags 0 | pass | read-only people/family |
| EditSelf only | blocked | `/external/limited-access` |

Gate must run in **both** `PageInit.php` and `AuthMiddleware` (`isAuthFlowExemptPath()` so login still works). `MvcAppFactory` always adds `AuthMiddleware`.

Read is default: `canReadFamily` / `canReadPerson` return true. Pass the real IDs; the parameters are reserved ABAC hooks.

`usr_EditSelf` defaults to 0. Grant it on purpose in `UserService::normalizeAccessMode()`.

## Object scope

- Sensitive family routes (profile, geolocation, notes, writes): `FamilyMiddleware` + `canViewFamily()`.
- Avatar / nav / photo: `FamilyReadMiddleware`. `FamilyMiddleware` takes no constructor arguments (Slim resolves `::class` with none, otherwise 500).
- `PersonMiddleware` only loads the entity. Put `canEditPerson($id, $famId)` in the **handler**, because ManageGroups member routes share the middleware.
- Notes: `NotesRoleAuthMiddleware` is the door. Non-admin non-author private notes are omitted from the timeline, not 403. Admin sees full note content.

## MVC + menu

App-level middleware = lowest **read** role. Add the write middleware on POST routes. A module-level AddEvents gate 403s the calendar for View-only users.

A page, the API it calls and every menu entry, dashboard button and action-menu item that leads to it use the same permission. A hidden control over an open route is a hole. A visible control that ends in 403 is a bug.

Apply a route group's role middleware once, on the group, so a denied user gets 403 before any entity middleware answers 404.

## What each permission covers

- Work on a person or family (including assigning properties) needs Edit Records. Work on a group needs Manage Groups.
- Defining shared catalogs (properties, property types, list options) is admin-only.
- Report, letter and label pages follow ordinary staff access (the entry gate above). Bulk extraction is a separate decision in `docs/decisions/0002-rbac-redesign.md`.

## Retiring a permission

Move each caller to the permission that matches its data, drop the flag from the schema in the release's upgrade script (`db-schema-migration.md`), and write a release note telling administrators which users lose access and what to grant first. A dropped flag cannot be recovered from the database.

## PR Permission Audit — required before merge

If the diff touches `User.php`, `AuthMiddleware`, `PageInit.php`, `Menu.php`, family/person/notes middleware or routes, or any `*RoleAuthMiddleware`:

1. EditSelf-exclusive cannot reach internal pages.
2. Zero-permission user can read people/family and cannot write.
3. Family profile / geolocation / notes stay behind `canViewFamily()`.
4. Menu entries, dashboard buttons and action menus agree with the route middleware.
