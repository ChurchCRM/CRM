---
title: "Groups MVC Guidelines"
intent: "Design and implementation patterns for Groups MVC apps (SundaySchool, Congregation)"
tags: ["groups","mvc","routing","service-layer"]
prereqs: ["routing-architecture.md","service-layer.md","php-best-practices.md"]
complexity: "intermediate"
---

# Groups MVC Guidelines

Purpose: Define structure, routing, services, and permissions for new group-focused MVC apps (`sundaySchool`, `congregation`).

Quick checklist:
- Create `src/groups/<name>/routes/` with route files for UI and API.
- Place views in `src/groups/<name>/views/` using PhpRenderer.
- Add service(s) in `src/ChurchCRM/Service/` (e.g., `GroupService`, `AttendanceService`).
- Use Perpl ORM Query classes and TableMap constants for DB work.
- Apply `GroupRoleAuthMiddleware` or `AuthenticationManager` checks where appropriate.

API design:
- Keep group-scoped endpoints under `/groups/<app>/api/` or `src/groups/<app>/routes/api/`.
- Return standardized JSON via `SlimUtils::renderJSON()` and errors via `SlimUtils::renderErrorJSON()`.

Security & permissions:
- Check `User::isManageGroupsEnabled()` and object-level `canEditPerson()` when mutating memberships.
- Audit membership changes with `LoggerUtils` and include actor/context.

Migration notes:
- Start with adapters that call existing `api` services, then extract logic into `GroupService`.
- Provide compatibility shims in `src/api/routes/people/` that forward to group endpoints and return deprecation headers.

Testing:
- Add unit tests for `GroupService` and Cypress e2e tests for group enrollment flows.

References: `routing-architecture.md`, `service-layer.md`, `api-development.md`.
