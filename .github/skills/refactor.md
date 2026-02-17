---
title: "Refactor Patterns"
intent: "Guidance for refactoring legacy code to services and MVC structure"
tags: ["refactor","service-layer","mvc"]
prereqs: ["php-best-practices.md","service-layer.md"]
complexity: "intermediate"
---

---
title: Refactor & Migration Skill
summary: |
  Guidance and a per-feature checklist for migrating legacy PHP pages to
  Admin MVC, converting inline SQL to Perpl ORM, adding service-layer logic,
  and adding tests required before a refactor can be considered complete.
---

**Purpose**

- **Goal:** Provide a repeatable, feature-focused checklist teams can follow
  to migrate one CRM feature at a time (People, Families, Groups, Events,
  Finance, Reports, Admin, Plugins) from legacy pages to the modern Admin MVC
  + Service + ORM architecture used in ChurchCRM.

**Scope**

- **Per-feature:** Each feature migration contains coordinated tasks across PHP
  (legacy page removal), Admin MVC view + route creation, Service layer
  implementation, Perpl ORM conversions, tests (unit/API/Cypress), i18n, and
  CI validation.
- **Completion criteria:** All items in the feature checklist below satisfied
  and tests passing locally and in CI; code reviewed and a PR opened against
  `master` or the agreed integration branch.

**How to use this skill**

- Tackle one feature at a time. For each feature, create a branch named:
  `fix/<feature>-refactor-<short-desc>` and open incremental PRs.
- Add issue cards to the migration project and tag with `section:<feature>`,
  `type:mvc`, `type:orm`, `type:tests`, `difficulty:easy|medium|hard`.

**Perpl ORM & Service Rules (always follow)**

- **Use Query classes:** Replace `RunQuery()` with Perpl ORM `*Query` calls.
- **withColumn():** Use TableMap `COL_` constants when calling `withColumn()`.
- **addForeignValueCondition():** Pass `TABLE_NAME` + column name string
  (NOT TableMap COL_ constant) to `addForeignValueCondition()`.
- **Lifecycle signatures:** Keep strict type signatures on overrides
  (e.g., `preSave(ConnectionInterface $con = null): bool`).
- **Sanitize inputs:** Use `InputUtils::filterInt()` and other `InputUtils`
  helpers instead of raw `$_POST`/`$_GET` access.

**General Coding Rules for All Feature Migrations**

- **Service layer first:** Implement domain code in `src/ChurchCRM/Service/`.
- **Use RedirectUtils:** For all redirects use `RedirectUtils::redirect(...)`,
  `RedirectUtils::securityRedirect(...)` or `RedirectUtils::absoluteRedirect(...)`.
- **API errors:** Use `SlimUtils::renderErrorJSON()` in API routes.
- **No raw SQL:** Do not introduce new `RunQuery()` or raw SQL; refactor
  existing ones into Perpl ORM queries or Service methods.
- **i18n:** Add `gettext()` strings and after changes run `npm run locale:build`
  and `npm run build` before opening a PR.

**Per-Feature Checklist (apply to each feature)**

- **Planning & Inventory**
  - **Inventory files:** List all legacy pages in `src/` for the feature.
  - **RunQuery inventory:** Extract `RunQuery()` hits for those files (use the
    existing `.github/triage/runquery-inventory.csv`).
  - **Difficulty tag:** Mark each RunQuery entry `easy|medium|hard`.

- **PHP (legacy) changes**
  - **Identify public endpoints** currently served by top-level `src/*.php`.
  - **Remove business logic** from templates: move to Service methods.
  - **Migrate view state:** Ensure initial UI state is rendered server-side
    (avoid flash state that JS must fetch on first paint).

- **Admin MVC**
  - **Routes:** Add new admin routes under `src/admin/routes/<feature>.php`.
  - **Views:** Create view templates in `src/admin/views/<feature>.php` and
    render initial state with PhpRenderer.
  - **Permissions:** Use `AuthenticationManager::getCurrentUser()` checks and
    `RedirectUtils::securityRedirect()` where appropriate.

- **Service Layer & ORM**
  - **Service methods:** Create `src/ChurchCRM/Service/<Feature>Service.php`.
  - **Query conversion:** Replace `RunQuery()` with `*Query::create()` calls.
  - **Selective loading:** Use `->select([...])` to fetch only needed columns.
  - **Avoid N+1:** Pre-fetch related data (joins or in-memory grouping).

- **Testing & QA**
  - **Unit tests:** Add unit tests for Service methods where practical.
  - **API tests:** Add Cypress API tests under `cypress/e2e/api/private/...`
    for the endpoint(s) added.
  - **UI tests:** Add Cypress UI tests under `cypress/e2e/ui/<feature>/`.
  - **Session login:** Use `cy.setupAdminSession()` or other session helpers.
  - **Run locally:** Clear logs and run `npx cypress run` for the new tests.

- **i18n & Locale**
  - **Add gettext()** to all new strings and run `npm run locale:build`.
  - **Commit** updated `locale/messages.po` and built assets.

- **Docs & Review**
  - **PR checklist:** Add a PR description with Summary, Changes, Why,
    Files Changed (per guide). Include reviewer suggestions and test steps.
  - **Logging:** Use `LoggerUtils::getAppLogger()` in services for important
    events and errors.

- **Acceptance Criteria (to mark feature refactor complete)**
  - All legacy pages for the feature are removed or stubbed with redirects.
  - Service methods cover all business logic previously in pages.
  - All `RunQuery()` instances for the feature are replaced with ORM or
    documented exceptions (with security justification).
  - Unit/API/UI tests added and passing in CI.
  - `npm run locale:build` executed and resulting `messages.po` committed.
  - Documentation updated and a PR opened with proper branch naming.

**Heuristics for Difficulty Classification**

- **Easy:** Read-only SELECT statements; simple CRUD pages; one-off reports
  that don't use dynamic SQL or temporary tables.
- **Medium:** Pages with moderate joins, some business logic, or multiple
  small queries that need to be consolidated into one service call.
- **Hard:** Dynamic SQL generation, DDL, destructive scripts, or pages that
  embed multi-step transactions and cross-domain side effects.

**Project & Issue Labels (recommendation)**

- `section:<feature>` (e.g., `section:people`, `section:finance`)
- `type:mvc`, `type:orm`, `type:tests`, `type:i18n`
- `difficulty:easy|medium|hard`

**Core vs Plugin Evaluation (required)**

- **Evaluate integration needs:** For each feature/migration determine whether
  it should remain a core feature or be implemented as a plugin. If the
  feature interacts with third-party services (payments, external APIs,
  authentication providers), or provides optional integrations, prefer
  implementing it as a plugin to keep the core lean and maintainable.
- **Decision criteria:** Consider the following when deciding:
  - **Coupling:** High coupling to external APIs or vendor SDKs → Plugin.
  - **Optionality:** If site instances may not use the feature → Plugin.
  - **Security/Compliance:** External integrations with strict compliance
    requirements may be isolated in a plugin.
  - **Release cadence:** Features requiring independent release cycles →
    Plugin.
- **Implementation guidance:** When migrating to a plugin, place business
  logic in `src/plugins/<plugin-name>/` and expose admin UI under the plugin
  admin routes. For core features, continue under `src/admin/` and
  `src/ChurchCRM/Service/`.

**Branching & PR Guidance**

- Branch naming: `fix/<feature>-refactor-<short-desc>`.
- Keep PRs small and reviewable; prefer incremental PRs that migrate small
  areas (e.g., one legacy page → MVC + Service + tests) rather than giant
  monolith PRs.

**Checklist Template (copy into every issue/PR body)**

1. Inventory files changed
2. Service created/modified
3. ORM conversions applied (list queries replaced)
4. Tests added (unit/API/UI)
5. i18n strings added & `locale:build` run
6. CI green locally and in remote

**Next steps (how to run this skill in the repo)**

- Step 1: Pick a feature and create an issue using the labels above.
- Step 2: Use `.github/triage/runquery-inventory.csv` to find candidate files.
- Step 3: Classify entries `easy|medium|hard` and create issue cards.
- Step 4: Implement Service first, then MVC route + view, convert queries,
  then add tests and i18n, finally remove legacy pages or add redirects.

**References**

- See `src/Include/Functions.php` for global helpers used during migration.
- Follow Perpl ORM patterns described in the repository's standards docs.

---
Generated: Refactor skill for feature-by-feature CRM migration.
