---
title: "Slim MVCs — Routes, Uses, Security & Migration Guidance"
intent: "Inventory Slim MVCs and provide migration guidance for group apps"
tags: ["slim","mvc","routing","migration","security"]
prereqs: ["routing-architecture.md","slim-4-best-practices.md"]
complexity: "intermediate"
---

**Slim MVCs — Skill: Routes, Uses, Security & Migration Guidance**

Overview
- **Purpose:** Inventory current Slim MVCs (route groups / folders), explain what each is used for, list security and operational considerations, and provide a migration plan to create new MVC apps/groups (SundaySchool, Congregation) and move appropriate `v2` and `api` functionality into them.

Current MVCs (folders & primary route files)
- **API (`/api`)**: [src/api/routes](src/api/routes) — REST endpoints used by the frontend and external clients (people, families, calendar, finance, auth-related endpoints). Security: authenticated via app auth middleware; uses JSON error handling via centralized helpers. Keep backward-compatible API surface while migrating.
- **v2 UI (`/v2`)**: [src/v2/routes](src/v2/routes) — new(er) UI routes, server-side endpoints supporting React/TS frontend (people, person, family, cart, calendar, user). Security: page-level permission checks and server-side rendering of initial state; interacts with services.
- **Admin (`/admin`)**: [src/admin/routes](src/admin/routes) — Admin pages and admin-only API endpoints (system config, logs, upgrade, user-admin). Security: requires admin roles; sensitive operations.
- **Finance (`/finance`)**: [src/finance/routes](src/finance/routes) — Finance-specific MVC for dashboards, pledges, reports, deposit/payment APIs. Security: finance role gating; audit-sensitive.
- **Kiosk (`/kiosk`)**: [src/kiosk/routes](src/kiosk/routes) — Kiosk UI and kiosk-scoped `/api` group. Security: kiosk token/cookie flows and limited actions.
- **External / Public (`/external`, `/public`, `/register`, `/system`)**: [src/external/routes](src/external/routes), public API routes under `src/api/routes/public` — public-facing endpoints (register, calendar feeds, verification). Security: often unauthenticated or special token-based flows; must validate inputs strictly.
- **Plugins (`/plugins`)**: [src/plugins/routes](src/plugins/routes) and `src/plugins/core/*/routes` — plugin-provided MVCs. Security: plugin isolation; plugin routes may bypass assumptions if they use globals.
- **Setup (`/setup`)**: [src/setup/routes/setup.php] — installer/setup flows; sensitive, run once.
- **Session/auth flows (`/session`)**: [src/session/routes] — login, password reset, MFA flows.

Notes about how routes are organized
- Routes are grouped by purpose and placed under `src/<area>/routes` (see `src/v2/routes`, `src/api/routes`, `src/admin/routes`, etc.).
- Business logic should live in `src/ChurchCRM/Service/` (Service layer) and not in route handlers. Migration must preserve or move service code, not just routes.
- Per repository conventions: use Perpl ORM Query classes, `SlimUtils::renderErrorJSON()` for API errors, and `RedirectUtils` for page redirects.

Security & operational considerations (general)
- **Auth & permissions:** Many routes rely on role-based checks (admin, finance, edit records). When moving routes between MVCs, ensure the same middleware and permission checks remain or are migrated.
- **Backward compatibility:** External clients or the React frontend may call `/api` or `/v2` endpoints. Maintain stable routes (or provide compatibility redirects/shims) during migration.
- **Locale / i18n:** Adding gettext() strings requires running `npm run locale:build` and `npm run build` before committing translations. Migration that changes UI strings must follow this process.
- **Tests & CI:** Update or add Cypress tests for new endpoints; clear logs before running tests per repo policy.
- **Plugin compatibility:** Plugins register routes; changing core route namespaces may break plugins. Provide compatibility layer or plugin migration docs.

Design for new MVCs: Groups apps (SundaySchool, Congregation)
- **Goal:** Create two new MVC areas under `src/groups/sunday-school` and `src/groups/congregation` (or `src/groups/sundaySchool`, `src/groups/congregation`) that host group-related UI and APIs for these domains.
- **What to include in each MVC:**
  - Group management UI (list, enroll/unenroll people, schedules)
  - Group-specific API endpoints (memberships, rosters, attendance)
  - Service-layer logic in `src/ChurchCRM/Service/Group*Service.php` (re-use/extract from existing `people-groups` logic)
  - Permission checks (manage groups / edit records) and audit logging

Mapping v2 & api into new Groups MVCs
- **Move candidates from `v2` and `api`:**
  - `v2/routes/people.php`, `v2/routes/person.php`, `v2/routes/family.php` → only UI pieces that present group membership should be adapted to call new Groups services/APIs; do NOT move generic person/family CRUD unless grouping-specific UI demands it.
  - `api/routes/people/people-groups.php` and `api/routes/people/people-persons.php` (membership-related endpoints) → move or replicate into `src/groups/<app>/routes` (group-scoped endpoints).
  - Calendar and attendance flows that are group-specific → migrate into the group MVCs (e.g., `v2/routes/calendar.php`, `api/routes/calendar/*` where relevant).
- **Keep where they belong:**
  - Core `api` endpoints for `persons`, `families`, `payments`, `deposits`, and other cross-cutting domain APIs stay under `src/api/routes` unless strongly group-scoped.
  - Admin functionality remains under `src/admin/routes`.

Cost, Risk & Effort estimate
- **Low-effort, low-risk tasks:**
  - Adding new route groups under `src/groups/*` that call existing services (minimal changes).
  - Adding adapters/wrappers that route `api` calls to new group APIs while keeping original `/api` routes as compatibility shims.
- **Medium effort / moderate risk:**
  - Extracting service logic from route handlers into `Service` classes when logic currently lives inline; requires tests and regression validation.
  - Updating frontend code (v2 React) to call new group endpoints; moderate work if the frontend is modular.
- **High effort / high risk:**
  - Removing or renaming existing `/api` routes used by external integrations — breaking API clients and plugins.
  - Migrating authentication/authorization semantics between route namespaces (must preserve security semantics exactly).

Risks & mitigations
- **Broken clients / plugins:** Provide compatibility shims (keep old `/api` endpoints forwarding to new services) and deprecate with a timeline.
- **Permission regressions:** Add automated tests covering role scenarios; review `User::can*` usage and object-level checks.
- **i18n drift:** Run locale build, update PO files, and include translations in PRs.
- **Data migration:** Prefer no DB schema changes; keep data models but introduce group-specific join tables or metadata incrementally.

What will be left behind (and cleanup steps)
- Legacy route handlers in `src/v2/routes` that are generic person/family endpoints — keep as compatibility but mark as deprecated.
- Admin APIs remain in `src/admin/routes` (no change).
- Plugin routes in `src/plugins` — may need opt-in migration documentation.
- Ensure `src/api/routes/public` endpoints remain unchanged for public flows.

Recommended migration checklist (practical steps)
1. Create `src/groups/sundaySchool/routes/*` and `src/groups/congregation/routes/*` with initial route skeletons that call existing services.
2. Extract or wrap group-related business logic into `src/ChurchCRM/Service/GroupService.php` (or `SundaySchoolService`, `CongregationService`) re-using `PersonService`/`GroupService` patterns.
3. Add unit/integration tests + Cypress e2e tests for group flows.
4. Add compatibility endpoints in `src/api/routes/people/` that forward to new group endpoints and log deprecation warnings in headers.
5. Update `v2` frontend to call new group APIs (feature-flag roll-out).
6. Run `npm run locale:build` and `npm run build` if new UI strings were added.
7. Communicate plugin migration steps and document API deprecation timeline.

Decision notes
- Prefer incremental rollout: add new MVCs and adapters first, then update UI to consume them, then remove old endpoints once usage is verified.
- Do NOT change database ownership or core person/family CRUD unless required — keep domain model stable.

Deliverables
- This skill document (the file you are reading).
- A short mapping of which `v2` and `api` endpoints should move (see section "Mapping v2 & api into new Groups MVCs").

If you want, I can now:
- create the `src/groups/` skeleton with route files for `sundaySchool` and `congregation`,
- extract the existing `people-groups` service code into `src/ChurchCRM/Service/GroupService.php`, and
- add compatibility routes in `src/api/routes/people/` that forward to the new group routes.
