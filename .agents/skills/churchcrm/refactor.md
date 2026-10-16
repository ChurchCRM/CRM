---
title: "Refactor Patterns"
intent: "Guidance for refactoring procedural pages to services and MVC structure"
tags: ["refactor","service-layer","mvc"]
prereqs: ["[[php-best-practices]]","[[service-layer]]"]
complexity: "intermediate"
---

# Refactor & Migration

Move one feature at a time (People, Families, Groups, Events, Finance, Reports, Admin, Plugins) from a top-level `src/*.php` page to Admin MVC + Service + Perpl ORM. Page conversion steps: `admin-mvc-migration.md`.

## Workflow

1. Pick a feature and open an issue. Labels: `section:<feature>`, `type:mvc|orm|tests|i18n`, `difficulty:easy|medium|hard`
2. Find candidates: `grep -rn "RunQuery" src/<feature pages>`
3. Branch `fix/<feature>-refactor-<short-desc>`. Keep PRs small: one page becomes MVC + Service + tests
4. Build in this order: Service, MVC route and view, query conversion, tests, i18n, then delete the page file and update every caller to the new route

## Rules

- Domain code lives in `src/ChurchCRM/Service/<Feature>Service.php`
- Queries use Perpl `*Query::create()`; convert existing `RunQuery()` calls to ORM or Service methods
- `withColumn()` takes TableMap `COL_` constants. `addForeignValueCondition()` takes `TABLE_NAME` and the column name string
- Lifecycle overrides keep strict signatures: `preSave(?ConnectionInterface $con = null): bool`
- Inputs go through `InputUtils::filterInt()` and the other `InputUtils` helpers
- Redirects use `RedirectUtils::redirect()`, `securityRedirect()` or `absoluteRedirect()`
- API errors use `SlimUtils::renderErrorJSON()`
- Services log through `LoggerUtils::getAppLogger()`
- Select only needed columns (`->select([...])`) and pre-fetch related data
- Render initial UI state server-side
- Routes live in `src/admin/routes/<feature>.php`, views in `src/admin/views/<feature>.php`. Permission checks use `AuthenticationManager::getCurrentUser()` and `RedirectUtils::securityRedirect()`
- Moving `==` to `===`: cast `mysqli_fetch_array()` / `extract()` / `$_GET` / `$_POST` values first (`code-standards.md` → "Strict vs Loose Comparisons")
- Deleted `Functions.php` helpers live in `ChurchCRM\Utils\*` (`php-best-practices.md`)

## Difficulty

- **Easy:** read-only SELECTs, simple CRUD, reports without dynamic SQL or temporary tables
- **Medium:** moderate joins, some business logic, several small queries to fold into one service call
- **Hard:** dynamic SQL, DDL, destructive scripts, multi-step transactions with cross-domain side effects

## Core or plugin

Build the feature as a plugin in `src/plugins/<name>/` when it:

- couples to external APIs or vendor SDKs (payments, authentication providers)
- is optional per site
- carries its own compliance requirements
- needs an independent release cycle

Other features stay in `src/admin/` and `src/ChurchCRM/Service/`. Plugin rules: `plugin-system.md`.

## Tests and i18n

- Unit tests for Service methods where practical
- Cypress API specs in `cypress/e2e/api/private/...`, UI specs in `cypress/e2e/ui/<feature>/`
- Sign in with `cy.setupAdminSession()`
- Wrap new strings in `gettext()`; locale terms are generated on merge (`i18n-localization.md`)

## Done when

- The feature's old pages are deleted and every caller uses the MVC route
- Service methods hold all business logic
- Every `RunQuery()` in the feature is ORM, or a documented security-justified exception
- Unit, API and UI tests pass locally and in CI
- The PR body carries: files changed, service created or changed, queries converted, tests added, CI status

## SQL upgrade files

Edit only the `src/mysql/upgrade/` script for the release in preparation (named for the version in `package.json`). Published scripts for earlier releases are fixed. A DB change for the current release goes in `src/mysql/upgrade/<version>.sql`, registered in `src/mysql/upgrade.json`, and the release notes mention it.

Upgrades run the installed release's code in memory while the new files land on disk, so earlier-release scripts define upgrade paths for existing installs. An emergency edit to an earlier script needs the risk written in the PR body and explicit maintainer approval.

```bash
ls -1 src/mysql/upgrade | sort
git tag --list | sort --version-sort
```

## Removing methods that `doUpgrade()` calls

`ChurchCRMReleaseManager::doUpgrade()` runs in one PHP request:

1. The installed release's `ChurchCRMReleaseManager` is loaded into memory
2. The new release's files are moved into place
3. The in-memory `doUpgrade()` continues and autoloads every class it references from the new files

A method the in-memory code calls must therefore exist in the new release too. A removed method fails with `Call to undefined method` after the file moves and DB migrations have succeeded; the upgrade completed but the user sees an error banner.

Before removing a public static method from `src/ChurchCRM/Service/*` or any class `ChurchCRMReleaseManager` touches:

```bash
git grep -n "MyClass::myMethod\b" <tag-of-previous-release>
```

Search the previous three minor release tags. When an earlier release's `ChurchCRMReleaseManager` calls the method, keep it as a no-op shim until all supported upgrade sources have moved past it, or test the upgrade path from the oldest supported version. When a shim is not viable, the release notes say the upgrade shows a harmless error toast, that it succeeded, and that the user should reload.
