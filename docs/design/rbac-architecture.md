# RBAC architecture

**Status:** Draft for maintainer review ([#10923](https://github.com/ChurchCRM/CRM/issues/10923)). Nothing here is implemented. Decisions marked **Decided** were answered by George on 2026-10-29; everything else is a recommendation.
**Inputs:** [RFC 0002](../decisions/0002-rbac-redesign.md), audits [#10515](https://github.com/ChurchCRM/CRM/issues/10515), [#10516](https://github.com/ChurchCRM/CRM/issues/10516), [#10519](https://github.com/ChurchCRM/CRM/issues/10519), and the code on `master`.

## 1. Decisions

| ID | Decision | Effect on RFC 0002 |
|---|---|---|
| A1 | **One parity cutover.** Roles become the only permission source in one release, merged only when every V1 check gives the same answer. Scoped and sensitive rules are added feature by feature afterwards through the same service. | Replaces the per-feature manifest and dual-path plan in "Architecture decision" (2026-10-10). Matches §9 (2026-10-27). |
| A2 | **Auto-mirror at upgrade.** Each user receives one built-in role per V1 switch that is on. Access is identical the next morning; the church does nothing. | Supersedes "never silently backfill active V2 assignments" (§9 step 3). |
| A3 | **Drop the V1 permission columns at cutover.** The upgrade converts them to role assignments and removes them in the same script. Rollback is a database restore. | Supersedes "preserve legacy `usr_*` flags during 7.x" (§9 step 2). |
| A4 | **Legacy Administrator role.** Every account with `usr_Admin` today gets it. It holds every permission that exists on cutover day and is **frozen**: permissions added later are not added to it. | Settles the D2 "administrator wildcard" question for existing admins. |
| A5 | **New Administrator template is core system administration only** (users, roles, settings, system). Finance, people, groups and the rest are separate roles a church adds. | Settles the D2 wildcard question for new admins: no wildcard. |
| A6 | **Assigning roles:** a holder of `roles.assign` may give any role to another user, never to themselves. Roles holding a sensitive permission need a second approver. Every change is audited and the person is notified. | Settles the D2 grant ceiling and self-escalation rule. |
| A7 | **Rebuilt setup wizard with an Owner account ("admin 0").** The wizard asks for the real password (no shipped default) and which roles to create. Every install has exactly one Owner. | New. Replaces the seeded `admin`/`changeme` account. |
| A8 | **Owner is a true superuser,** including highly confidential and safeguarding content. It may assign roles to itself and cannot be deleted or demoted. | **Exception to P6**: the RFC's P6 row needs an amendment naming the Owner. Mitigation in §6. |

## 2. V1 permission table (what exists today)

V1 authorization is 13 switches plus three implicit rules. Almost every check goes through a predicate on `User` (`src/ChurchCRM/model/ChurchCRM/User.php`), which is the seam the cutover uses (§5).

### 2.1 Switches

| # | Switch | Stored in | Predicate | What it gates (callers on `master`) |
|---|---|---|---|---|
| 1 | Admin | `user_usr.usr_Admin` | `isAdmin()` | The whole `/admin` app (`AdminRoleAuthMiddleware`), users, settings, plugins, imports, logs, email log, text messages, CSV export, kiosk admin, property types, other users' private notes (`canReadPrivateNotes()`); and implies every switch below |
| 2 | Add Records | `usr_AddRecords` | `isAddRecordsEnabled()` | New person and family, cart-to-family, donated items and paddle numbers (tracked in #10684) |
| 3 | Edit Records | `usr_EditRecords` | `isEditRecordsEnabled()` | Person/family editors, properties on people, Why Came, verify report, group member properties, Sunday School edits |
| 4 | Delete Records | `usr_DeleteRecords` | `isDeleteRecordsEnabled()` | Delete person and family (`DeleteRecordRoleAuthMiddleware`, `SelectDelete.php`) |
| 5 | Manage Groups | `usr_ManageGroups` | `isManageGroupsEnabled()` | Groups, group properties, group cart, Sunday School admin, events tied to groups |
| 6 | Finance | `usr_Finance` | `isFinanceEnabled()` | Deposits, pledges, envelopes, all finance reports, finance search, giving on person and family pages. Also needs `bEnabledFinance` |
| 7 | Manage Fundraisers | `usr_ManageFundraisers` | `isManageFundraisersEnabled()` | Fundraisers. Also needs `bEnabledFundraiser` |
| 8 | Manage Ministries | `usr_ManageMinistries` | `isManageMinistriesEnabled()` | Every ministry and team (volunteer v2). Also needs the volunteer v2 flag |
| 9 | Manage My Ministries | `usr_ManageMyMinistries` | `isManageMyMinistriesEnabled()` | Ministries listed for the login in `volunteer_scope_vscp` |
| 10 | Notes | `usr_Notes` | `isNotesEnabled()`, `canReadNotes()` | Notes on person and family pages and timeline (non-private) |
| 11 | Edit Self | `usr_EditSelf` | `isEditSelfEnabled()`, `isEditSelfExclusive()` | Own record and own family. **Alone, it shuts off every other switch** (portal-only) |
| 12 | Add Events | `userconfig_ucfg` `bAddEvent` | `isAddEventEnabled()` | Create events and calendars (`AddEventsRoleAuthMiddleware`) |
| 13 | Email (mailto) | `userconfig_ucfg` `bEmailMailto` | `isEmailEnabled()` | Email-all and mailto links (`EmailRoleAuthMiddleware`) |

### 2.2 Implicit rules

| Rule | Where | Parity requirement |
|---|---|---|
| Any signed-in staff account reads every person and family | `canReadPerson()`, `canReadFamily()` return `true` | Every role, including a role with no permissions, keeps directory read |
| Edit Self alone means portal-only | `isEditSelfExclusive()` | The mirrored Edit Self role must override every other role exactly as today |
| Module flags gate on top of permissions | `bEnabledFinance`, `bEnabledFundraiser`, volunteer v2 | A flag still turns the module off for everyone, including Owner |

### 2.3 Gaps the inventory already shows

Recorded so the cutover does not copy them by accident; each is fixed in its own issue, not in the cutover.

- `isAdmin()` gates features that are not system administration: CSV export, the email log, text messages, other users' private notes. Under A5 these need their own permissions (§4.3).
- `usr_DeleteRecords` is checked by fundraiser and volunteer deletes (#10684, #10688).
- Custom field security now goes through `CustomFieldService` ([#10952](https://github.com/ChurchCRM/CRM/pull/10952)); the service maps the 13 switches by name, so it moves to the registry unchanged.

## 3. Target model

### 3.1 Permission registry

One code-owned registry (`src/ChurchCRM/Authorization/PermissionRegistry.php`) lists every permission key. Keys are lowercase and dot-separated (`people.update`, `finance.deposits.manage`). Each entry declares:

- `key`, a stable string never reused
- `module`, the feature flag that must be on, if any
- `sensitivity`: `ordinary` or `sensitive`; `sensitive` permissions need a second approver to assign (A6)
- `since`, the release that added it; used for the frozen Legacy Administrator (A4)

Unknown keys deny. Routes do not invent keys.

### 3.2 Roles

| Kind | Who edits it | Examples |
|---|---|---|
| Built-in role | ChurchCRM, versioned in code, immutable | the 13 mirror roles, Administrator, Legacy Administrator |
| Church template | ChurchCRM ships it; a church copies it to change it | Office Staff, Finance Manager, Pastor (RFC §3 catalog) |
| Church role | The church | copies of templates, or built from built-in roles |

A user may hold several roles. The effective permission set is the **union** of their roles' grants, then **intersected** with module flags, Edit Self exclusivity, scope and field rules (RFC D2 baseline).

### 3.3 Grants

A grant is one indivisible unit: `permission` + `scope`. Phase 1 has two scopes:

- `all`: the whole church
- `my-ministries`: the rows in `volunteer_scope_vscp` for the user, answered by `VolunteerAuthorizationService` as today

Roles combine whole grants; a scope from one role never attaches to an action from another. New scope types arrive only with a policy implementation and negative tests (RFC §9 F0 contract).

### 3.4 Principals

| Principal | Notes |
|---|---|
| Owner | Exactly one per install. Superuser (A8). Cannot be deleted or demoted; ownership transfers only by the Owner to another active account, with re-authentication |
| Staff user | `user_usr` row with roles |
| Portal member | No `user_usr` row under member sign-in (#10464); not part of this model |
| Kiosk, API token | Device and service principals with their own scopes (RFC §8); unchanged in phase 1 |

## 4. Built-in roles at cutover

### 4.1 Mirror roles (A2)

One built-in role per V1 switch, holding exactly what that switch allows today:

| Role | Grants (scope `all` unless stated) |
|---|---|
| People: Add | `people.create`, `families.create`, `cart.to-family` |
| People: Edit | `people.update`, `families.update`, `people.properties.assign`, `whycame.update`, `people.verify`, `groups.member-properties.update` |
| People: Delete | `people.delete`, `families.delete` |
| Groups: Manage | `groups.manage`, `groups.properties.manage`, `sundayschool.manage` |
| Finance | `finance.*` permissions listed in the registry (module `bEnabledFinance`) |
| Fundraisers: Manage | `fundraisers.manage` (module `bEnabledFundraiser`) |
| Ministries: Manage All | `ministries.manage` (module volunteer v2) |
| Ministries: Manage Mine | `ministries.manage` scope `my-ministries` |
| Notes | `notes.read`, `notes.create` |
| Self Service | `self.update`, `self.family.update`; **exclusive** (§5.2) |
| Events: Add | `events.create`, `calendars.manage` |
| Email | `email.mailto` |
| Administrator | core administration, §4.3 |

### 4.2 Legacy Administrator (A4)

Every permission in the registry with `since` ≤ the cutover release, including what `isAdmin()` alone allows today (private notes of others, CSV export, logs). Frozen: a later release never adds to it. When a release adds permissions a Legacy Administrator lacks, that admin sees a one-time notice listing them.

### 4.3 Administrator (A5)

Core administration only. **Proposed** split of today's `isAdmin()`-only features, for review:

| Today admin-only | Proposed home |
|---|---|
| Users, roles, settings, system, plugins install/configure, backups, upgrade, logs | Administrator |
| CSV import | Administrator |
| CSV export, People Report CSV | new `export.people` (bulk export gate, #10516) |
| Email log, text messages | new `communication.log.read` |
| Other users' private notes | new `notes.private.read`, `sensitive` |
| Kiosk device management | Administrator |
| Property type definitions | Administrator |

## 5. Authorization service and the cutover seam

### 5.1 Service

```php
interface AuthorizationService
{
    public function can(Principal $actor, string $permission, ?ResourceContext $resource = null): Decision;
}
```

`Decision` carries `allowed` and a machine-readable `reason`. `ResourceContext` is built from server-loaded records only. The service loads a user's grants once per request and caches them; a role change takes effect on the next request (RFC S5).

### 5.2 The seam

The `User` predicates in §2.1 keep their names and signatures and change their bodies to ask the service, for example:

```php
public function isFinanceEnabled(): bool
{
    return $this->authorization()->can($this->principal(), 'finance.access')->allowed;
}
```

This keeps the ~200 call sites and the 13 role middlewares untouched in phase 1. The service implements the implicit rules of §2.2 itself:

- Self Service exclusive: a user whose only role is Self Service, or whose roles are Self Service plus nothing staff-granting, is denied every staff permission, exactly as `isEditSelfExclusive()` today.
- Module flags are checked inside `can()`, so a disabled module denies even the Owner.
- Directory read stays granted to every staff principal.

New code calls the service directly. Converting old call sites to `can()` happens feature by feature after the cutover, together with any scope or sensitivity rule that feature gains.

### 5.3 Single source of truth

After the cutover nothing reads the dropped columns. `npm run lint` gets a check, like `validate-custom-field-access.js`, that rejects new reads of `usr_*` permission columns, new `isAdmin()` calls outside the predicates, and permission keys missing from the registry.

## 6. Owner safeguards (A8)

The Owner can read everything, so its use is visible instead of restricted:

- Reads of `sensitive` resources by the Owner are written to the audit log (who, what record, when; never the content).
- Ownership transfer and Owner password or 2FA changes require re-authentication and notify the Owner's email.
- The admin dashboard shows the Owner account and its last sign-in to every Administrator.
- When "Login as User" ships (member sign-in §6.5), it never opens or acts as the Owner.

## 7. Data model (F0 schema, additive until cutover)

| Table | Columns | Notes |
|---|---|---|
| `rbac_role` | `id`, `key` (unique), `name`, `kind` (`builtin`, `template`, `church`), `version`, `copied_from` | Built-in rows seeded idempotently by key |
| `rbac_grant` | `id`, `role_id` FK, `permission_key`, `scope` | Unique (`role_id`, `permission_key`, `scope`) |
| `rbac_user_role` | `user_id` FK, `role_id` FK, `assigned_by`, `approved_by`, `assigned_at` | Primary key (`user_id`, `role_id`) |
| `rbac_audit` | `id`, `actor_id`, `subject_id`, `action`, `detail` JSON, `created_at` | Role changes, Owner sensitive reads, ownership transfer |
| `user_usr.usr_IsOwner` | boolean, at most one true | Enforced in the service and by the setup wizard |

All access goes through Propel models and the service; no page writes these tables directly (same rule and lint pattern as custom fields).

## 8. Cutover

1. **Upgrade script** (one script, run once):
   1. Refuse to run unless a backup was taken in the same upgrade session (the upgrade wizard's Backup step, `src/admin/views/upgrade.php`, becomes mandatory for this release).
   2. Seed the registry's built-in roles.
   3. For each user: one assignment per switch that is on (A2); `usr_Admin` users get Legacy Administrator (A4).
   4. Mark the Owner: the lowest-ID active Legacy Administrator who signed in most recently becomes Owner; the upgrade report names them and how to transfer ownership.
   5. Verify: for every user, the effective permission set computed from roles equals the set computed from the columns. If any user differs, abort and roll back the transaction.
   6. Drop the `usr_*` permission columns and the two `userconfig_ucfg` permission rows (A3).
2. **Parity gate before merge:** a fixture set covers every switch alone, every pair, Edit Self with each switch, admin, zero switches, and each module flag off. Each fixture runs the V1 predicates on `master` and the V2 predicates on the branch against the same database and must agree. Cypress specs run as one user per switch (RFC lesson 6).
3. **Rollback:** restore the backup and reinstall the previous release. The release notes say so plainly.

## 9. Setup wizard (A7)

The rebuilt wizard asks for church name, the Owner's name, email and password (no default), and which built-in roles or templates to create. It creates the Owner and nothing else; other accounts are created afterwards from the user editor. Existing installs are not re-run through the wizard; the upgrade marks an Owner (§8 step 1.4).

## 10. Roadmap

Phase 1 is built on a long-lived branch `rbac/phase-1` and merges to `master` once, when the parity gate passes. Each item is one PR into that branch.

| # | Issue (proposed) | Depends on | Exit evidence |
|---|---|---|---|
| 1 | Parity fixtures: V1 predicate answers for every switch combination | — | Fixture runner on `master` |
| 2 | Permission registry and built-in role definitions (code only) | 1 | Unit tests: every V1 predicate maps to registry keys |
| 3 | Schema and Propel models (§7), install SQL and upgrade script skeleton | 2 | Fresh install and upgrade both produce the same tables |
| 4 | `AuthorizationService` with union/intersection, Self Service exclusivity, module flags, request cache | 2, 3 | Unit tests with fake principals |
| 5 | Predicate seam: `User` predicates ask the service | 4 | Parity fixtures green against the service |
| 6 | Upgrade conversion, verification and column drop (§8) | 3, 5 | Upgrade tests from seeded V1 data, including abort on mismatch |
| 7 | User editor: role picker replaces the checkboxes; `roles.assign`, no-self, second approver for sensitive, audit, notice | 4 | Cypress per rule |
| 8 | Owner: flag, safeguards (§6), transfer flow | 3, 4 | Cypress and audit assertions |
| 9 | Setup wizard rebuild (§9) | 8 | Fresh-install Cypress run |
| 10 | Admin-only split (§4.3): new permissions for export, communication log, private notes | 2 | Callers moved; negative tests |
| 11 | Lint check for the single source of truth (§5.3) | 5 | Check fails on a bad input |
| 12 | Release notes, upgrade guide, docs tracking issue | 6–9 | Docs PR after release |

After phase 1: church templates and role copies; then per-feature migration of call sites to `can()` with scopes and sensitivity (RFC F3), starting where the audits found the most exposure (exports, notes, custom fields).

## 11. Still open

Asked one at a time as the design proceeds; recorded here until answered.

- The split in §4.3.
- Which permissions are `sensitive` at cutover (proposed: `notes.private.read` only).
- Owner selection on upgrade when several admins exist (§8 step 1.4).
- Church template catalog for the first release after phase 1.
- RFC D1, D3, D4, D5, D7, D8 are untouched by phase 1 and stay open.
