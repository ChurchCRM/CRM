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

`Menu.php` visibility must match the route middleware. Hidden menu + open route is a hole. Visible menu + 403 is a bug.

## PR Permission Audit — required before merge

If the diff touches `User.php`, `AuthMiddleware`, `PageInit.php`, `Menu.php`, family/person/notes middleware or routes, or any `*RoleAuthMiddleware`:

1. EditSelf-exclusive cannot reach internal pages.
2. Zero-permission user can read people/family and cannot write.
3. Family profile / geolocation / notes stay behind `canViewFamily()`.
4. Menu and middleware agree.
