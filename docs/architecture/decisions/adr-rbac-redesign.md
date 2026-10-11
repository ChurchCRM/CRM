# RFC: Next-Gen RBAC and Module-Based Access Control

Status: **Proposed — review required; no implementation approved**
Date: 2026-10-10
Parent: [#8591](https://github.com/ChurchCRM/CRM/issues/8591)

## Executive summary and goals
Replace scattered legacy user flags and inline access checks with a centralized, deny-by-default authorization layer, preserving existing installations through a staged migration. Use RBAC for module/action capabilities and narrowly scoped ABAC for record access. No external policy service is required. Priorities: privacy of giving and children, least privilege, secure defaults, mobile-friendly administration, and compatibility.

Non-goals: immediate removal of legacy columns; blanket row-level security; introducing a paid identity or authorization provider; changing user privileges in this RFC.

## Current state and linked work
- [#8591](https://github.com/ChurchCRM/CRM/issues/8591): role builder, module visibility, CRUD privileges, ministry scoping, backend enforcement.
- [#8758](https://github.com/ChurchCRM/CRM/issues/8758): proposed Propel role/permission junction tables and backfill.
- [#8759](https://github.com/ChurchCRM/CRM/issues/8759): middleware and feature checks.
- [#8760](https://github.com/ChurchCRM/CRM/issues/8760): admin role builder.
- [#8761](https://github.com/ChurchCRM/CRM/issues/8761): menu/inline check migration.
- [#8762](https://github.com/ChurchCRM/CRM/issues/8762): ministry scoping; explicitly requires RFC before implementation.

Verified on repository default branch: `orm/schema.xml` contains `person_per` and existing schema; `AuthenticationManager` uses a session authentication provider and request-scoped API-token provider, with inline `isAdmin()` checks; `MvcAppFactory` supports `roleMiddleware` but not a permission config; `Menu.php` derives visibility from multiple `User::is*Enabled()` predicates and includes plugin hooks. Proposed `AuthorizationService.php` was not found at the expected path. This is a targeted audit, **not** an exhaustive codebase-wide enumeration. Before implementation, inventory API route groups, legacy PHP endpoints, exports, background jobs, plugins, report downloads, and every authorization call-site with repository-wide search.

## Decision proposal
Use canonical **dot-separated** permission keys (matching #8758), not mixed dot/colon syntax: `people.read`, `people.create`, `people.update`, `people.delete`, `finance.read`, `finance.deposit.manage`, `event.read`, `event.update`, `group.manage`, `sundayschool.roster.read`, `note.update`, `self.update`, `admin.role.manage`. Catalog keys explicitly; aliases for colon syntax, if ever needed, must be resolved at one boundary. A permission grants an action; a module is visible if at least one appropriate read/navigation capability is granted. A feature flag enables availability, **never** grants permission.

Roles collect permissions; users can have multiple roles. Do not implement inherited role graphs in v1; composition avoids cycles and complex effective-permission debugging. Scope constraints are independent of role membership. Deny by default for unknown permission, missing resource, invalid scope, disabled module, or policy evaluation error. Existing unrestricted access during upgrade is handled by explicit compatibility mappings, not implicit allow.

### Logical architecture
```text
Browser / API / legacy PHP / plugins
             |
 Authentication (session or API token)
             |
 Policy Enforcement Point (route + service + query)
             |
 AuthorizationService / Policy Decision Point
   |          |              |
 Role grants  module flags   resource scope resolver
   |          |              |
 DB tables    config         group membership / ownership
             |
        allow / deny + reason -> structured audit events
```

### Proposed data model
```text
user_usr 1--* user_role_usrrol *--1 role_rol
role_rol 1--* role_permission_rolprm *--1 permission_prm
role_rol 1--* role_scope_rolscp *--1 scope_scp [future opt-in]
group_grp 1--* scope_scp [candidate; confirm schema]
```
Initial schema follows #8758: `role_rol` (id, stable key, name, built_in, timestamps), `permission_prm` (id, unique key, module, description), `role_permission_rolprm` (role_id, permission_id, unique pair), `user_role_usrrol` (user_id, role_id, unique pair). Use Propel mappings in `orm/schema.xml`, indexed foreign keys and deletion constraints. Keep existing `usr_*` columns throughout 7.x. Do not assume `group_grp` can model every ministry resource; decide after a schema and query audit. Avoid storing security-critical decisions solely in a client token.

## Interfaces
```php
interface AuthorizationService {
    public function can(User $actor, string $permission, ?ResourceContext $resource = null): bool;
    public function require(User $actor, string $permission, ?ResourceContext $resource = null): void; // 403
    public function effectivePermissions(User $actor): array;
}
```
`ResourceContext` is server-derived (resource type/id, ministry/group scope, owner, sensitivity); do not trust request-supplied scope IDs without checking their relationship to the resource. Route-level permission checks and service-layer resource checks are both required. List queries, counts, exports and bulk operations must use the same scoped predicates. Separate `401` unauthenticated from `403` authenticated-but-denied; avoid revealing sensitive record existence where appropriate. Existing middleware and `MvcAppFactory::create(['roleMiddleware'=>...])` remain operational during migration; introduce `permission` alongside them with documented Slim LIFO ordering.

## Session, tokens, caches and audit
Validate session/token identity on every request; recheck disabled accounts and authentication assurance for privileged operations. Request-local effective-permission caching is acceptable; role updates must take effect by next request. Any cross-request cache needs versioned invalidation. Do not serialize privileged permission grants into stale sessions. Use least-privilege API tokens with explicit scope checks. Log role assignment/revocation, permission changes, failed checks, actor, action, resource type, outcome and correlation ID, without logging confidential person, giving, or child data. Restrict audit-log access and define retention policy.

## Threat model
| Threat | Mitigation |
|---|---|
| Direct URL / API bypass | PEP on all routes plus service/resource authorization |
| IDOR / cross-ministry disclosure | server-derived scope and filtered queries; test lists, exports, counts |
| Privilege escalation through role editor | separate `admin.role.manage`, prohibit self-escalation, enforce grant ceiling and last-admin safeguard |
| Stale permissions | request-local cache and transactional updates |
| Plugin / legacy PHP bypass | explicit extension authorization contract and migration inventory |
| Unauthorized finance / child data | sensitive action keys, resource checks, least-privilege templates |
| Confused deputy / forged scope | bind actor, authenticated context and loaded resource in PDP |
| Logging leaks | structured minimal metadata and protected retention |

Administrator wildcard compatibility is proposed **only for built-in administrator**. Decide whether sensitive actions, tenant boundaries, and ministry scopes are exempt before implementation. Built-in roles cannot be deleted; whether their permissions are mutable needs a security decision (issue #8760 currently suggests yes).

## Database migration and rollout
1. Inventory existing user flags, user access behavior, middleware, routes, APIs, plugin hooks and reports; create a permission matrix and baseline tests.
2. Add additive Propel schema and SQL migration under `src/ChurchCRM/dbUpdates/`; inspect existing migration numbering first. Seed stable keys and built-in roles idempotently; backfill legacy flags transactionally.
3. Shadow-evaluate old and new decisions without changing access; compare differences and report mismatches without logging private data.
4. Introduce centralized PEP on representative finance, event and group routes; test allow/deny, missing resource, direct URL and API access.
5. Ship role administration with safeguards and permission-aware menus; migrate remaining inline checks incrementally.
6. Prototype opt-in ministry scoping for one resource type only after #8762 design approval. Audit indexes and EXPLAIN plans for high-volume queries.
7. Enable enforcement by module behind an operational rollout switch, collect regression evidence, and document rollback (switch back to legacy checks; preserve additive schema). Never use a switch that bypasses existing legacy authorization.

For each phase: test fresh install, upgrade with mixed legacy flags, inactive users, API tokens, portal-only users, finance users, multi-role users, plugin routes, translations, Cypress navigation, denial responses, and performance. Documentation in `docs.churchcrm.io` must follow shipped behavior, not speculative RFC features.

## Decisions requested from maintainers
1. Dot keys versus colon keys (recommend dots, matching #8758).
2. Built-in Administrator wildcard scope and sensitive-operation exceptions.
3. Whether built-in role permissions can be edited; recommend immutable built-in templates, clonable custom roles.
4. Whether group-based ministry scoping is adequate; require schema/query prototype first.
5. Compatibility sunset criteria for legacy flags; do not remove during 7.x.
6. Whether module switches are global operational flags or per-role visibility; recommend separate concepts.

## Acceptance criteria for RFC
Architecture and threat model reviewed; permission catalog and legacy mapping approved; complete call-site inventory and resource scope prototype assigned; phased issues updated; no runtime behavior changes in this PR.
