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


## Expanded cross-issue audit (2026-10-10)
The parent feature must reconcile historical and contemporary requests, not just #8758–#8762:
- [#3428](https://github.com/ChurchCRM/CRM/issues/3428) (closed stale): reusable roles, configurable default role for standard staff accounts, role assignment during creation, and sidebar cleanup. Closure is not proof of implementation.
- [#769](https://github.com/ChurchCRM/CRM/issues/769): enforce authorization near the ORM/data layer.
- [#10213](https://github.com/ChurchCRM/CRM/issues/10213): Email permission is missing from creation and Permissions card; `bEmailMailto` in `userconfig_ucfg` can disagree with displayed defaults. Backfill must preserve **effective** behavior, not just physical rows.
- [#9003](https://github.com/ChurchCRM/CRM/issues/9003): read-only staff access differs from portal-only mode.
- [#8977](https://github.com/ChurchCRM/CRM/issues/8977), [#9863](https://github.com/ChurchCRM/CRM/issues/9863), [#10463](https://github.com/ChurchCRM/CRM/issues/10463), [PR #10464](https://github.com/ChurchCRM/CRM/pull/10464): member portal, self-service sign-in, explicit staff-vs-member distinction and person-level sign-in management.
- [#10252](https://github.com/ChurchCRM/CRM/issues/10252), [#10259](https://github.com/ChurchCRM/CRM/issues/10259): module-owned report catalog; report discovery, downloads and exports inherit data-source permission.
- [#9525](https://github.com/ChurchCRM/CRM/issues/9525), [#9524](https://github.com/ChurchCRM/CRM/issues/9524): contact PII and non-attendee exports/email actions need dedicated grants.
- [#9952](https://github.com/ChurchCRM/CRM/issues/9952), [#9949](https://github.com/ChurchCRM/CRM/issues/9949): API routes span several prefixes, and prefix-based admin detection is unsafe.
- [#9704](https://github.com/ChurchCRM/CRM/issues/9704), [#9701](https://github.com/ChurchCRM/CRM/issues/9701): volunteer V1/V2 feature rollout is separate from authorization.
- [#9845](https://github.com/ChurchCRM/CRM/issues/9845): admin masquerade requires explicit permission, prohibition on impersonating admins, clear audit actor/subject separation.
- [#10191](https://github.com/ChurchCRM/CRM/issues/10191): plugin email integration must honor Email permission.
- [#10464](https://github.com/ChurchCRM/CRM/pull/10464) is an **unmerged proposal**; it must not be treated as existing behavior.

### Verified account creation and current storage
On `master`, `src/admin/routes/system.php` normalizes permission form inputs and calls `UserService::createUser($personId, $perms, $userName)`. `src/ChurchCRM/Service/UserService.php` verifies person existence, prevents duplicate account and username, writes user flags and password transactionally, explicitly persists `bAddEvent`, then sends `NewAccountEmail` with the generated password when email is configured. It does **not** write an equivalent explicit `bEmailMailto` row on create. The `User` model documents two permission stores: core `user_usr` flags and `userconfig_ucfg` rows (`bAddEvent`, `bEmailMailto`). `src/mysql/install/Install.sql` seeds a global `bEmailMailto` default and selected user rows. Existing `EditSelf` exclusive users are denied admin-shell module access; all-zero permission staff users are a distinct supported state.

Current core columns include `usr_Admin`, `usr_EditSelf`, `usr_AddRecords`, `usr_EditRecords`, `usr_DeleteRecords`, `usr_MenuOptions`, `usr_ManageGroups`, `usr_Finance`, `usr_ManageFundraisers`, `usr_ManageMinistries`, `usr_ManageMyMinistries`, and `usr_Notes`. The earlier Phase 1 role list omits some recently added ministry and volunteer capabilities: refresh the role/permission catalog from code, not the historical issue list.

### Module and role catalog to validate
Derive canonical capabilities from active navigation, routes and APIs for People/Families, Groups, Sunday School, Calendar/Events/Attendance, Communication/Email, Volunteer/Ministries, Deposits/Giving, Fundraising, Reports/Exports, Portal/Self, Settings/Admin, Plugins/Integrations, and Kiosk. Distinguish viewing, creation, update, delete, export, communication, configuration, privileged operations and scoped management. Example built-in templates for discussion: Member Portal, Read-only Staff, Record Editor, Group Leader, Volunteer Coordinator, Event Manager, Communications, Finance Reader, Finance Manager, Fundraising Manager, Church Administrator and System/Security Administrator. Do **not** automatically grant new templates to existing users. Define whether system administrator separation is meaningful in a single-church installation before introducing it.

### Integration contract with member sign-in PR #10464
Keep `person_per` and `user_usr` physically separate; account lifecycle and eligibility are **authentication** gates, not RBAC grants. Model portal-only versus staff explicitly; never infer portal-only from zero effective permissions. New member self-setup must grant portal access only, without staff role or API token. Staff account creation must require explicit role selection, with a documented read-only choice and configurable default only if approved. All role edits, disable-login actions, impersonation and invitations must enforce independent permissions and last-admin safeguards. PR #10464 proposes additional eligibility blocks, classification/age settings, and secure token setup: these remain pending decisions and must not be assumed deployed. Coordinate token purpose validation, transactional token consumption and session invalidation with sign-in design.

### Migration matrix and cutover contract
| Scenario | Data initialization | Effective access on cutover |
|---|---|---|
| Existing church upgrade | Additive tables; seed immutable permission catalog and built-in templates; snapshot every account's effective legacy flags and relevant userconfig rows; backfill explicit compatibility grants | Bit-for-bit equivalent allow/deny for each existing protected action; differences block enforcement |
| Fresh installation | Install schema includes RBAC tables and seeds identical to migrations; initial bootstrap admin gets administrator role transactionally | Admin access works; no default staff/module grants to members |
| New member setup (if #10464 approved) | Create `user_usr` at password setup and mark portal-only; no staff role, no API key | Portal only |
| New staff account | Explicit template selection and preview of effective permissions; never rely on implicit global userconfig defaults | Exactly chosen grants, including an explicit read-only staff template |
| Existing zero-permission staff | Preserve account classification and read-only access; no automatic conversion to portal | Same legacy access until intentional reassignment |
| Existing EditSelf-exclusive | Preserve exclusive portal gate; avoid stacking module roles | Portal only |
| Missing or inconsistent Email row | Resolve using existing effective permission evaluation and document exception report | No silent gain or loss |
| Existing admin | Backfill built-in Administrator with verified wildcard equivalence | Same admin access, with explicit decisions for new security-only operations |
| Rollback | Switch PDP to legacy compatibility mode; leave additive tables untouched | Prior behavior, with no orphaned grants or lost legacy flags |

Migration must be **idempotent**, tested against populated snapshots and clean installs, and must use a stable account-classification signal rather than an inferred all-zero role set. Introduce a schema/version marker for RBAC activation, a dry-run comparator, and a signed-off diff report. Shadow evaluation alone must not leak denied resources. Validate backfill of ministry/volunteer and feature-flag-derived permissions; distinguish a disabled module from a missing grant. Require route-level and resource-level tests for old and new accounts. The initial install SQL and upgrade migrations must produce equivalent tables, indexes, seeds and role mappings.

### Release gate
Do not implement #8758 without reconciling #10464 and the default staff role. Do not merge #10464's account changes without explicit RBAC compatibility review. No changes to live behavior in this RFC.
