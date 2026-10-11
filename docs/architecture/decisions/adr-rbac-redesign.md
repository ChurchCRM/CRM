# RFC: ChurchCRM authorization, identity and sensitive-record access

**Status:** Proposed for independent security and architecture review; no implementation approved.  
**Date:** 2026-10-10  
**Parent:** [#8591](https://github.com/ChurchCRM/CRM/issues/8591) · **Design PR:** [#10511](https://github.com/ChurchCRM/CRM/pull/10511)  
**Authority:** ChurchCRM/CRM code on the default branch; proposed member sign-in [#10464](https://github.com/ChurchCRM/CRM/pull/10464) is not shipped behavior.

## 1. Scope and terminology

This document is the **single reviewable design contract**, replacing earlier additive notes. It specifies target behavior, explicitly separates existing behavior from proposals, and identifies decisions that require maintainer approval. It does not assert that unimplemented protections exist.

- **Person:** `person_per` record; may have no account. **Family:** `family_fam` household/contact record, not proof of legal guardianship. **Account:** `user_usr` login linked to a person. **Principal:** authenticated account plus credential type and assurance, or explicitly approved service principal.
- **Role:** reusable collection of action permissions. **Module:** functional area; a feature flag determines availability, not authorization. **Scope:** allowed person/family/group/ministry/resources. **Classification:** independent sensitivity/visibility restriction on a record. **Policy decision:** server-side allow/deny with reason.
- **Staff:** authorized to enter the administrative application, including read-only staff. **Portal-only:** member self-service, never inferred from having zero staff permissions. One person may hold several church roles.
- **Must** denotes an implementation requirement **if this RFC is approved**. **Proposed default** is a review recommendation, not an approved product decision. **Open decision** blocks the relevant implementation phase.

### Non-goals
No immediate removal of legacy flags, automatic ministry-level isolation of every existing resource, paid identity provider, cross-church multi-tenancy, client-side authorization, or promise that in-process PHP plugins are sandboxed. This RFC makes no runtime, database or release changes.

## 2. Verified current behavior and known limitations

| Area | Verified on default branch | Consequence |
|---|---|---|
| Broad directory read | `User::canReadPerson(int)` and `canReadFamily(int)` return `true`; comments reserve row-level policy for future | A route calling them does **not** currently establish person/family confidentiality |
| Direct numeric ID | `src/people/routes/view.php` loads `PersonQuery::findPk(personID)` and calls `canReadPerson` | ID guessing is a security vector if record policy is absent; numeric IDs themselves are not credentials |
| Person lifecycle | `person_per` includes `per_DateDeceased` and `per_DateDeactivated` | Lifecycle is **not** a visibility classification |
| Username | `usr_UserName` has a unique index in `src/mysql/install/Install.sql`; `UserService` checks uniqueness on create/update | Keep username unique within installation |
| Emails | `per_Email`, `per_WorkEmail`, `fam_Email` have no unique constraint in install schema; `User::getEmail()` returns linked person's email | Contact/household email cannot be assumed unique, verified or suitable for recovery |
| Legacy grants | `user_usr` permission flags and `userconfig_ucfg` (including `bAddEvent`, `bEmailMailto`) coexist | Backfill effective access, not just physical flag values |
| Notes | `Note::isVisibleTo(User)` permits author and delegates private access to `canReadPrivateNotes` (admin described); deprecated `isVisible(int)` is author-only | Existing privacy semantics differ by method; audit every caller |
| MVC/plugins | `MvcAppFactory` supports `roleMiddleware`; plugin menus document that hiding links is not route security | Introduce consistent server-side checks; menus are presentation only |
| Account changes | `UserService::updateUser()` can change username; `User` contains password and 2FA recovery methods | Recovery-email exploit is **not confirmed** until reset lookup and contact-edit paths are traced |

**Audit limitation:** These are targeted inspections, not a complete route/schema/upgrade/test inventory. Do not infer security or exploitability from uninspected endpoints. Related historical findings: [#8999](https://github.com/ChurchCRM/CRM/issues/8999), [#9036](https://github.com/ChurchCRM/CRM/issues/9036), [#10206](https://github.com/ChurchCRM/CRM/issues/10206), [#10213](https://github.com/ChurchCRM/CRM/issues/10213).

## 3. Church-first 80/20 experience

A church must be able to use templates without constructing a complex access graph. Roles **compose** (no inheritance graph in v1); role assignment does not override sensitive-record restrictions.

| Suggested template | Typical permitted workflow | Never implied |
|---|---|---|
| Member / portal-only | Own approved profile, approved household view, personal events/volunteering | Staff directory, all household private data |
| Volunteer | Assigned shifts, check-in or attendance operations | All children or unrelated ministries |
| Group leader | Assigned roster, attendance, appropriate messaging | All church records, private notes |
| Ministry coordinator | Teams, scheduling, approved group operations | Finance or account security |
| Read-only staff | Broad ordinary person/family directory | Editing, protected records, confidential notes |
| People staff/editor | Create/update ordinary people and families | Credential recovery changes or protected-person bypass |
| Finance reader/manager | Relevant giving and deposits (separate read/write) | Automatic private pastoral access |
| Church administrator | Normal configuration and staff administration | Automatic access to highly confidential content or unchecked credential takeover |

These are **design templates**, not confirmed seeded roles. Initial implementation should use a small set of composable templates; extra combinations should be custom roles, not a large fixed matrix. The maintainer must approve the final built-in catalog and mapping.

### Default directory policy
- Ordinary **staff** retain broad access to **standard** people and families, including inactive records where normal staff workflow requires it. Portal-only users are **not** staff and must remain limited to their own approved relationships.
- Record lifecycle (active, inactive, deceased) and record visibility (standard, restricted, possibly protected) are independent.
- **Proposed default:** existing records migrate as `standard` to avoid surprising access loss; restricted records are invisible to ordinary directory readers, including existence, search, direct URL, family references and exports. Authorized designated staff can access according to a specific policy.
- **Open decision D1:** exact meaning of restricted vs protected, whether administrators may access protected records, who can classify/declassify, whether any placeholder is shown, and whether family-level classification propagates. **No implementation of restricted visibility until D1 is approved.**

## 4. Authorization contract (all entrypoints)

Canonical permission keys are lowercase dot-separated, e.g. `people.read`, `people.update`, `notes.read`, `notes.update`, `notes.export`, `account.recovery_email.change`, `account.credentials.reset.other`, `plugin.<id>.use`. Keys are centrally registered, not created ad hoc by routes. Unknown actions and policy failures **deny**. A feature flag cannot grant access.

```php
interface AuthorizationService {
    public function decide(Principal $actor, string $action, ResourceContext $resource): Decision;
}
```

`Decision` contains `allowed`, machine-readable `reason`, and internal audit metadata. `Principal` contains account/service identity, authentication method, token scopes and assurance. `ResourceContext` is constructed from **server-loaded** resources, including parent person/family, classification, owner and scope; do not trust client-supplied relationship IDs.

**Effective allow requires all applicable checks:** authenticated active principal; appropriate portal/staff boundary; module enabled; action granted by a role/legacy compatibility mapping; resource in scope; record/field sensitivity permits action; credential scopes permit action; step-up authentication where required. Any deny wins. Administrators may bypass ordinary role grants **only if D2 approves**; never silently bypass protected-record or credential rules.

**Enforcement:** authentication first, then `RequirePermission` route middleware, then service/resource authorization and query-scoped filtering. Slim LIFO order must be explicitly integration-tested. Use the same policy in MVC, API, legacy PHP, Member Portal, plugin endpoints, background jobs, reports, exports, PDFs, search, autocomplete, counts, bulk actions, photos, attachments, calendar and related-record views. Lists must filter *before* pagination/counting. Every by-ID GET/PUT/PATCH/DELETE must check the loaded record. For hidden records, public response should not confirm existence; internal logs may record denial without sensitive content. Use 401 for unauthenticated and 403 for ordinary forbidden requests, with indistinguishable 404 where existence is protected. Authorization failures must not partially commit writes.

**Open decision D2:** administrator wildcard, role grant ceiling, built-in role immutability, break-glass workflow and last-admin safeguards. **Proposed:** built-in templates immutable and clonable; no silent self-escalation; sensitive actions never implied by `admin`.

## 5. Identity, email, member accounts and guardians

1. `usr_UserName` stays unique. A person may lack an account. Personal and family contact emails may be missing or shared; **do not** add uniqueness constraints to `per_Email` or `fam_Email`.
2. If email is used as a login identifier, introduce/identify a **verified account-bound unique login email**; never treat a shared family email as proof of identity or select one of several people with the same contact address. Conflicts require explicit user resolution.
3. Editing contact email (`people.contact.edit`) must **not** implicitly change verified login/recovery identity. Recovery destination changes need independent authorization, reauthentication/step-up, proof of control of the new destination, notice to the old destination, expiration of pending reset tokens and audit. Privileged-account changes require stronger review; token-based callers may not reset credentials by default.
4. Keep `person_per` and `user_usr` separate; self-service account setup and age/eligibility decisions from [#10464](https://github.com/ChurchCRM/CRM/pull/10464) remain unmerged and require coordination. Portal-only accounts receive no staff privileges or automatic API token.
5. Household membership does not prove guardianship. **Proposed default:** guardians may perform only approved child-related tasks through an explicit verified guardian relationship; emergency contacts and other household adults receive no automatic sensitive-child access. Custody disputes, blended households, consent, medical/safeguarding notes, pickup permissions, minors reaching adulthood and guardian revocation need separately reviewed workflows.
6. **Open decision D3:** where verified login/recovery email is stored, what existing recovery endpoints currently trust, uniqueness/conflict migration, guardian relationship schema and policy, child-age transitions and staff-assisted recovery requirements. **Do not build these flows from assumption.**

## 6. Notes, custom fields and sensitive data

A feature grant does not imply access to every record. Evaluate **record-specific** read/create/update/delete/share/classify/export policy and parent-record scope. Private notes currently have conflicting visibility methods; inventory callers and preserve legacy semantics until a migration decision. Avoid leaks via list/count, search snippets, related-record timelines, notifications, print, API and plugins. Audit classification, sharing, access and modifications without logging note body.

**Proposed:** highly confidential pastoral/counseling notes have no automatic administrator content bypass; specifically authorized custodians only, with separately approved emergency access. **Open decision D4:** existing private-note semantics (admin/author), author access after role loss, explicit sharing, and whether a new confidentiality classification is required. No silent reinterpretation of existing `nte_Private`.

Custom fields require field-level read/update/export permissions in addition to parent-record access. Inventory person/family custom-field schema and current security flags. Enforce consistently across forms, API, search, reports, PDF/CSV and plugins; hiding a field in UI is not authorization. Finance, child safeguarding and contact PII require separate sensitivity policies.

## 7. Plugins and integrations

Separate lifecycle capabilities (`plugin.install`, `plugin.activate`, `plugin.configure`, `plugin.uninstall`) from everyday capabilities (`plugin.<id>.use`, `plugin.<id>.report.read`, `plugin.<id>.export`). Plugin menu visibility is never sufficient. Route, API, report, scheduled job, webhook, configuration and browser-exposed data each require explicit policy. Check plugin boot/activate hooks, `getClientConfig`, secret exposure and direct ORM/SQL use.

**Trust boundary:** a PHP plugin in the same process with the same DB credentials is trusted server-side code; RBAC middleware **cannot** sandbox its direct SQL, filesystem or network access. Strong plugin isolation requires separate process/API and least-privilege credentials. **Open decision D5:** plugin trust/installation policy, minimum manifest permission declarations and whether untrusted plugins are supported.

## 8. API credentials and service jobs

Named, independently revocable API tokens: random secret displayed once; only hash stored; explicit expiry, owner, action scopes and optional resource/ministry restrictions. Authorization is intersection of principal's current permissions, token scopes, module availability and resource policy. Read-only tokens cannot write, export PII or change recovery/roles/tokens merely because they can read a person. Never place bearer tokens in public browser JavaScript; use a server-side proxy or purpose-built public endpoint.

Audit **actual** existing token mechanisms, including the My Settings API Tokens UI [#10043](https://github.com/ChurchCRM/CRM/issues/10043) and legacy `usr_apiKey`; do not assume one is the only implementation. Legacy migration must retain supported integrations until a documented cutover. Scheduled jobs require explicit service identity and scoped capabilities, not an unaudited administrator shortcut.

## 9. Data model and migration

**Proposed initial Propel tables** (coordinate with [#8758](https://github.com/ChurchCRM/CRM/issues/8758)): `role_rol`, `permission_prm`, `role_permission_rolprm`, `user_role_usrrol`; stable permission keys, unique junction pairs, FK/index/delete behavior, idempotent seeds. Optional future scope tables need a prototype; do not assume existing group relationships model every ministry. Additional identity/guardian/visibility/token schema **must be designed and approved separately** rather than guessed here.

1. Inventory legacy flags, effective permissions, all routes/callers and migration history (`orm/schema.xml`, install SQL, actual upgrade mechanisms); establish baseline tests.
2. Add schema additively; verify fresh-install and upgrade parity. Preserve legacy `usr_*` flags during 7.x; never map unknown legacy behavior to a broad grant.
3. Backfill role mappings from **effective** legacy behavior, including `userconfig_ucfg` defaults; report conflicts. Do not automatically assign new staff templates to portal-only users.
4. Shadow-evaluate decisions without changing behavior; measure differences and investigate security regressions.
5. Enforce representative routes and service/query paths, then incrementally migrate all entrypoints; hide UI only after backend protection.
6. Release role editor with grant ceilings, last-admin protections and audited changes. Pilot ministry scoping only after query and index review.
7. Keep rollback to legacy checks possible during phased rollout, without bypassing pre-existing checks. Existing visibility remains standard unless D1 approves another migration. Revoke or refresh authorization cache/session on role or credential changes.

**Open decision D6:** exact migration file location/numbering, initial role mappings, permission catalog completeness, compatibility sunset, performance limits and rollout switch semantics. Verify repository conventions first.

## 10. Security acceptance matrix (must become automated tests)

| Scenario | Required result |
|---|---|
| Read-only staff views standard active/inactive person | Allowed; no edit |
| Portal-only member guesses another person's numeric ID | Denied without existence leak |
| Staff guesses restricted person/family ID or reaches via search/export | Denied consistently across entrypoints |
| Authorized staff views restricted person | Only with explicit approved policy |
| User with `notes.read` guesses another author's private note | Denied unless record policy permits |
| Admin views highly confidential note | Denied unless explicitly authorized under D4 |
| Person editor changes another user's contact email | Must not change account recovery destination |
| Attempt to recover admin account after contact-email edit | Cannot redirect recovery through unverified contact edit |
| Two adults share household email | Separate identities; no ambiguous login/recovery |
| Guardian accesses own child versus unrelated child | Only approved relationship/actions; no implicit household privilege |
| Volunteer accesses assigned roster versus another ministry | Scope enforced on list and by-ID |
| Read-only token calls write/export/recovery endpoint | Denied |
| Plugin user opens admin configuration URL | Denied; plugin runtime trust limitations documented |
| Role revoked during active session | Next request reflects revocation |
| Fresh install versus upgraded church | Same intended effective authorization and seeded templates |

Also test cross-family links, hidden-person count/thumbnail leakage, child becomes adult, 2FA reset, password recovery races, duplicate email, inactive/disabled account, bulk operations, reports, plugin SQL trust boundary, and negative authorization tests on every HTTP verb. Unit-test policy with fake principals; integration-test route middleware and SQL filtering; Cypress/Playwright test representative member/staff workflows. Do not mark these passing until evidence exists.

## 11. Review gates and ownership

**Gate A — design approval (this PR):** reviewer checks consistency, threat model, role UX, and resolves D1–D6 with George. No production implementation from an unresolved security decision.

**Gate B — inventory/prototype:** Claude or assigned engineer provides a route/permission/DB inventory, actual recovery-email flow trace, notes caller audit, guardian model audit, plugin/API-token inventory, migration plan and concrete test matrix with source paths. Independent reviewer verifies it.

**Gate C — implementation PRs:** focused phases: schema/service; middleware and query scoping; people/family visibility; notes/custom fields; identity/recovery/guardians; plugin/token policy; admin UI; migration and documentation. Security-sensitive changes need dedicated review and tests. No merge/release without George's approval.

### Independent reviewer checklist

- Can a typical church use templates without managing dozens of flags?
- Does every grant have an unambiguous action, resource and scope?
- Are staff versus portal-only and family versus guardian relationships explicit?
- Can any direct-ID, API, report, plugin, search, or export path bypass person/note/field restrictions?
- Can contact editing cause account takeover through recovery?
- Does administrator status bypass any sensitive policy without an explicit decision?
- Is the actual legacy behavior preserved through upgrade, including email and notes semantics?
- Are plugin in-process trust and API-token constraints represented honestly?
- Are D1–D6 answered before the corresponding phase starts?
- Are negative tests and rollback criteria implementation-ready?

## 12. Source issues and design dependencies

[#8591](https://github.com/ChurchCRM/CRM/issues/8591) parent; [#8758](https://github.com/ChurchCRM/CRM/issues/8758) schema; [#8759](https://github.com/ChurchCRM/CRM/issues/8759) middleware; [#8760](https://github.com/ChurchCRM/CRM/issues/8760) role UI; [#8761](https://github.com/ChurchCRM/CRM/issues/8761) menu/inline migration; [#8762](https://github.com/ChurchCRM/CRM/issues/8762) scopes; [#10464](https://github.com/ChurchCRM/CRM/pull/10464) member sign-in proposal; [#9003](https://github.com/ChurchCRM/CRM/issues/9003) staff read; [#9036](https://github.com/ChurchCRM/CRM/issues/9036) notes policy; [#8999](https://github.com/ChurchCRM/CRM/issues/8999) note IDOR; [#1886](https://github.com/ChurchCRM/CRM/issues/1886) custom fields; [#10213](https://github.com/ChurchCRM/CRM/issues/10213) email grant; [#10043](https://github.com/ChurchCRM/CRM/issues/10043) API tokens; [#10191](https://github.com/ChurchCRM/CRM/issues/10191) plugin email; [#10252](https://github.com/ChurchCRM/CRM/issues/10252) reports; [#10206](https://github.com/ChurchCRM/CRM/issues/10206) authorization gaps; [#769](https://github.com/ChurchCRM/CRM/issues/769) data-layer ACL.

**Review status:** proposal only. D1–D6 are explicitly open, not hidden assumptions. No runtime implementation or security remediation is claimed.
