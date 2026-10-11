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


## First-class enforcement for MVC, API, custom fields, plugins and API credentials (2026-10-10)

### Verified integration points
- `src/ChurchCRM/Slim/MvcAppFactory.php` accepts `roleMiddleware` at module level; introduce an explicit permission policy interface/route metadata, with module default and route-level overrides. Preserve Slim 4 LIFO ordering. Route access checks are mandatory for both HTML and JSON.
- `src/plugins/index.php` gates `/management` and `/api` with `AdminRoleAuthMiddleware`, registers enabled plugin routes via `PluginManager::registerPluginRoutes()`, and applies common authentication. Plugin routes currently rely on plugin-specific enforcement.
- `src/ChurchCRM/Plugin/PluginInterface.php::getMenuItems()` accepts legacy permission names; its doc explicitly warns menu visibility is **not** access control. It also exposes `getHeadContent()`, `getFooterContent()`, `getClientConfig()`, settings, lifecycle hooks and plugin routes.
- `src/ChurchCRM/Plugin/PluginManager.php` distinguishes core and community plugins and exposes registry risk/permission metadata; that metadata must not be mistaken for runtime sandbox enforcement.
- `src/ChurchCRM/model/ChurchCRM/User.php::updatePassword()` rotates the current API key. `src/mysql/install/Install.sql` defines `usr_apiKey` as a unique field on `user_usr`. Confirm the full current API-token implementation, including My Settings tokens, before finalizing migration.

### Policy contract and testability
Use a single `AuthorizationService::decide(Principal, Action, ResourceContext): Decision` called from declarative `RequirePermission` route middleware, service/query enforcement, and plugin adapters. Principal carries account, auth method, token ID and scopes. Decision carries allow/deny and safe reason code (never sensitive record data). MVC: redirect/403 per established conventions; API: deterministic 401 unauthenticated versus 403 unauthorized, no sensitive payload. Tests should inject fake principals and policies without database dependencies; integration suites exercise middleware ordering and deny-by-default; Cypress covers role UI, deep links and menu visibility. Every route and plugin entrypoint needs a cataloged permission or an explicit documented public/authenticated-only classification. No silent default allow.

### Custom-field policies
Audit `person_custom_master`, `family_custom_master`, field-security columns, form editors, queries, exports, reports, search, API serialization, portal and plugin consumption. [#1886](https://github.com/ChurchCRM/CRM/issues/1886) explicitly requests role/permission levels per custom field; [#5771](https://github.com/ChurchCRM/CRM/issues/5771) requests export support. Recommend field-specific read/write/export policies with owner-module permission as a prerequisite, explicit sensitivity defaults and server-side filtering. Hiding a field on the form must not permit API, CSV, PDF, search, report or plugin leakage. Preserve existing field security semantics during migration; document discrepancies before backfill.

### Plugin permission model and trust boundary
Separate capabilities: `plugin.install`, `plugin.activate`, `plugin.configure`, `plugin.uninstall` (privileged); `plugin.<id>.use`, `plugin.<id>.report.read`, `plugin.<id>.export`, and optional plugin-defined permissions for ordinary users. A plugin's menu item, route, API, report, background job and webhook must declare policies; missing declarations deny access. Module disabled means unavailable regardless of user grants. Settings pages must not become accessible merely because a user can run the plugin.

**Important security limitation:** a third-party PHP plugin executing in the same application process with the same database credentials is trusted server-side code; middleware does **not** sandbox direct Propel/SQL access, PHP file access, or outgoing network calls. Do not promise database-level isolation. A safe restricted-plugin model requires a separate process/service and least-privilege data API or a deliberately constrained extension runtime; otherwise label installed community plugins privileged and require admin approval. Audit plugin `boot()`, `activate()`, `getClientConfig()` and injected HTML/JS for data/secret exposure. Registry-declared risk/permissions are descriptive until enforced. No raw secrets in client configuration. Scheduled jobs need a service principal and explicit scoped privileges, not an implicit administrator.

### Scoped API credentials
Support multiple independently revocable named credentials with `token_id`, owner, hashed secret, explicit permission scopes, optional resource/ministry restrictions, expiry, created/last-used/revoked metadata. Show secret only once; allow rotation. Effective access = **account permissions ∩ token permissions ∩ resource policy ∩ module availability**. A read-only token may access allowed read routes but never mutate, export, reveal restricted fields, access administration, or escalate through plugin routes. `read` must not implicitly include `export` or `contact.read`. Tokens are never authorized to create broader tokens or modify their own scopes; owner revocation, deactivation and permission reductions take effect promptly. Treat an integration token as a bearer secret, not safe to embed in public client-side JavaScript; public webpages require a server-side proxy or separately designed public/anonymous access endpoint with strict rate limiting.

Preserve existing `usr_apiKey` during an additive transition, with compatibility authentication and user-permission ceiling. Inventory newer API Tokens UI/storage before designing its cutover; do not claim the single legacy column is the only token system. Provide an opt-in migration and eventual deprecation, no automatic privilege expansion. Validate token auth, HTML session, plugin routes, report export, and custom fields with the same policy matrix.

### Required audit before implementation
1. Enumerate MVC/API/legacy/plugin routes, current middleware, and direct service/database access; generate a route-to-permission inventory.
2. Inspect custom-field schema, existing field-level checks, serializers and every export/report path.
3. Inspect core/community plugin manifests, route registration, lifecycle hooks, raw SQL/Propel, client config and plugin API use; classify trusted-code risks.
4. Inspect all API token tables, UI, authentication providers, rotation and revocation paths; test legacy compatibility.
5. Test a role x resource x credential x entrypoint matrix (allow, deny, revoked, disabled, scoped, missing policy) with unit/integration/Cypress suites.

## Sensitive notes: mandatory record-level security policy (2026-10-10)

Related issues: [#9036](https://github.com/ChurchCRM/CRM/issues/9036) (define coherent Notes visibility policy), [#8999](https://github.com/ChurchCRM/CRM/issues/8999) (note-by-ID IDOR), [#10206](https://github.com/ChurchCRM/CRM/issues/10206) (authorization gaps), [#8690](https://github.com/ChurchCRM/CRM/issues/8690) (visitation notes), [#1879](https://github.com/ChurchCRM/CRM/issues/1879) (historic permissions redesign).

**Verified current behavior:** `src/ChurchCRM/model/ChurchCRM/Note.php` has `isPrivate()`, `isVisibleTo(User)`, and deprecated `isVisible(int)`. Current `isVisibleTo` grants public-note visibility (with route-level Notes authorization), grants author visibility on private notes, and delegates other private-note access to `User::canReadPrivateNotes()`; its comment documents administrator access. The deprecated method instead treats private notes as author-only. This is a real semantic mismatch and needs caller-by-caller review. A note's visibility check does not itself establish parent person/family resource access. Do not claim the IDOR is fixed until endpoint and tests are checked.

**Design principle:** `notes.read` grants access to the *feature*, never blanket access to note contents. `NotePolicy::decide(principal, action, note)` must evaluate actor identity, parent person/family scope, note privacy/classification, author, explicit sharing and credential scope; the most restrictive rule wins. Apply the same policy to list/count, by-ID GET, create, edit, delete, timeline, search, exports, PDFs, API, Member Portal, plugins, background jobs, backups and notifications. Avoid leaks through counts, snippets, search indexes and error differences.

**Approval required:** whether administrators may read private notes by default, whether authors retain access after role loss, whether notes can be shared with specific pastoral roles/teams, and whether highly confidential pastoral/counseling notes require a separate class. Recommended default for a newly introduced highly confidential classification: no administrator content bypass; access by explicitly authorized custodians only, with separately audited emergency access if approved. Do not silently reinterpret existing `nte_Private` during upgrades.

**Write controls:** distinct create, read, update, delete, share, classify, export and privileged override permissions. No author-only exemption from the account's parent-record scope without an explicit approved policy. Prevent changing private to public, changing ownership or deleting someone else's note without corresponding privilege. Preserve author/edited-by history, timestamp, classification and auditable access/changes; never put sensitive note body in authorization logs.

**Migration and tests:** inventory note schema, all `isVisible`/`isVisibleTo`/`canReadPrivateNotes` callers and middleware; record current effective access matrix, including admin, author, staff, portal and token users. Keep legacy semantics until a reviewed migration policy and user communication are approved. Add explicit security regression tests for guessed note IDs, cross-family access, admin versus private, role revocation, scoped token, plugin API, report/export, field/list redaction, and privacy toggles. Deny by default for new sensitive-note classes.

## Account recovery and email changes as privileged security operations (2026-10-10)

**Threat:** changing a person's contact email, login identifier, or password-recovery destination can enable account takeover if recovery flows trust that value. Treat this as a security boundary, especially for staff/admin accounts. Do not equate `people.edit` with `account.credentials.manage`.

**Verified code touchpoints:** `src/admin/routes/system.php` derives a default new-user login name from a person's email and calls `UserService::updateUserWithConfig()` for account updates; `src/ChurchCRM/Service/UserService.php::updateUser()` validates and changes `UserName`; `src/ChurchCRM/model/ChurchCRM/User.php` implements password resets and 2FA recovery methods. These facts do **not** establish which email the forgot-password flow currently uses: trace actual recovery endpoints, person email editors, member sign-in PR #10464, and all email normalization/lookup paths before claiming an exploit.

**Required policy:** distinct permissions for `people.contact.edit`, `account.login.change.self`, `account.recovery_email.change.self`, `account.credentials.reset.other`, `account.credentials.manage.other`, `account.2fa.reset.other`, `account.role.assign`, and `account.tokens.manage`. No inherited ability to change credentials or recovery destinations from contact editing. For self-service recovery-email change: reauthenticate or verify existing factor, verify new destination with single-use expiring token before activation, notify old destination, and invalidate pending recovery tokens. For staff-initiated changes: separate explicit high-trust privilege, auditable reason and step-up authentication; changing privileged accounts may require second approval or an approved break-glass process. Do not send account-recovery secrets to an unverified new address. If contact email doubles as login email, introduce a verified account-level binding or controlled synchronization flow; do not silently allow person-edit workflows to redirect password recovery.

**Security tests:** staff editor changing another user's person email, admin-account target, portal self-service, shared family email, duplicate emails, race between recovery and email change, unverified/pending email, existing sessions, revoked token, compromised scoped API credential, and account role elevation. Check whether a token can modify contact email or credential recovery and deny these actions unless explicitly designed for trusted interactive sessions. Preserve existing recovery behavior until audited and migrated safely.
