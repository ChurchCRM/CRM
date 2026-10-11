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

## Approved church-facing product decisions (George, 2026-10-10)

These are **approved product requirements**, not proof of current behavior or approval to implement unreviewed migrations. Technical details listed as D1–D8 remain open.

| ID | Approved requirement | Outstanding implementation detail |
|---|---|---|
| P1 | Ordinary staff can see ordinary directory records; selected people may be hidden/restricted | Restricted-read policy, family propagation, disclosure handling (D1) |
| P2 | Private notes: author plus authorized staff; highly confidential notes: explicitly shared recipients only | Authorization/sharing model, legacy migration (D4) |
| P3 | Verified guardians manage approved child data; authorized pickup is separate | Relationship proof, custody, transition rules (D3) |
| P4 | Personal email must be unique **when supplied**; family email remains distinct | Existing duplicates, validation, nulls, uniqueness enforcement and recovery binding (D3) |
| P5 | New staff accounts start with read-only ordinary directory access | Exact template grants, upgrade behavior (D6) |
| P6 | Administrators manage systems but do not automatically access confidential pastoral/safeguarding content | Ordinary wildcard and emergency access (D2/D4) |
| P7 | Only pastor/designated pastoral leadership can restrict/unrestrict a person | Delegation verification, audit, restricted-record read policy (D1) |
| P8 | Simple church-role templates by default; optional advanced custom permissions | Final template catalog and role editor design (D2/D6) |
| P9 | Churches may designate an existing group as trusted Pastoral Leadership; protect membership and explicitly configure granted capabilities | Grant and approval workflow (D7) |
| P10 | Track each person's church voting eligibility as a structured membership attribute, **independent of groups and application RBAC roles**; Church Board remains an ordinary group | Eligibility rules, effective dates, authorization, privacy and migration (D8) |
| P11 | Ship versioned built-in role templates; church admins customize via church-owned copies, not by editing system originals. Users may hold multiple roles; advanced permissions are optional. | Template catalog, composition/conflict rules, scope editor, upgrades, sensitive-grant approval and effective-access preview (D2/D6) |

**Conflict resolution:** P4 supersedes the earlier RFC proposal to permit shared `per_Email` values as a permanent target. It does **not** establish that existing data is unique, mandate email for every person, or authorize immediate database constraints. The reviewer must assess the impact on existing churches and [member sign-in PR #10464](https://github.com/ChurchCRM/CRM/pull/10464). Household `fam_Email` is not subject to personal-email uniqueness merely because it may match someone's email.

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

| Proposed built-in template | Intended responsibility | Does **not** automatically grant |
|---|---|---|
| Member / Household (portal-only) | Approved own and household self-service | Staff directory, unrestricted family private data |
| Volunteer | Assigned shifts and narrowly scoped tasks | Unrelated people, all children, exports |
| Check-In Volunteer | Assigned event/class check-in and safe pickup workflows | Full directory, finance, membership administration |
| Group / Team Leader | Assigned roster, attendance and approved communications | Other groups, private notes, bulk export |
| Ministry Coordinator | Assigned ministries, teams and schedules | Unscoped finance or user management |
| Read-Only Staff | Ordinary directory records | Edit, protected people, confidential notes, bulk export |
| Office Staff / People Editor | Ordinary person and family updates | Credential recovery, restricted-person bypass, membership date by default |
| Membership Officer | Authorized membership classification/date and approved reports | Voting eligibility unless separately granted, confidential notes |
| Pastor / Pastoral Care | Approved pastoral workflows and explicitly shared notes | All highly confidential/safeguarding records or blanket admin access |
| Finance Assistant | Assigned giving-entry tasks | Finance configuration, broad exports, pastoral records |
| Finance Manager | Authorized giving, deposits and financial reporting | Unrelated confidential notes or unrestricted person export |
| Church Administrator | Configuration, users, role assignments and ordinary system administration | Highly confidential content, sensitive overrides or automatic bulk export |

These are **role-template design candidates**, not currently seeded roles and not final grants. A kiosk is a **device principal** and an API integration is a **service principal** with its own credential, operation and scope policy, not a human role. Board membership and church governance positions do not automatically create application permissions. One individual may have several role assignments for small-church workflows.

### Approved template lifecycle and church customization (P11)

1. **System-owned versioned defaults:** ChurchCRM ships a small, understandable set of role templates with stable identifiers, documented purpose, explicit action grants and default scopes. Templates are read-only to churches; upstream versions may evolve with release notes and migration review. Exact catalog and grants are subject to persona, GHSA, kiosk, export and administrator audits.
2. **Customize by copying:** An authorized church administrator selects **Customize / Create church role**, creating a church-owned copy with its own name, description, source template/version, explicit permissions and scopes. They may also create a custom role from scratch in advanced mode. A customized role is not silently rewritten when a built-in template changes on upgrade.
3. **Explicit scope:** A grant answers **what action, on which resource/field, for whose people or ministry, through which allowed operation**. E.g., check-in for an assigned class does not imply access to every child or every family; ordinary directory read does not imply CSV/print/bulk export. Sensitive grants (protected persons, confidential notes, membership/voting governance, finance, full export, trusted-group management) require separate deliberate authorization and appropriate auditing; no implicit admin bypass for highly confidential content.
4. **Composed roles, not role inheritance:** A person may hold multiple roles. Combine only explicitly granted capabilities within each grant's own scope. Do **not** union a global scope from one role with a sensitive action from another to produce an unintended cross-product privilege. Record-level sensitivity and explicit confidentiality restrictions still apply. Conflicts and override policy remain an implementation decision D2.
5. **Effective-access preview and explanation:** Before saving an assignment or edited church role, show a church-friendly summary of effective **view / edit / print / filtered export / bulk export / administer** permissions and scope, highlighting newly granted sensitive access and who else holds the role. Provide an accessible, localized, mobile-usable UI and understandable denial reasons that do not reveal hidden records. No UI-only security: every data path must enforce the same policy server-side.
6. **Versioning and upgrade safety:** Store the built-in source and revision used by church-owned copies. On upgrades, preserve custom role grants, report changes in built-in defaults, and offer a reviewed comparison/rebase path; never silently escalate sensitive privileges or strip legitimate workflows. Migrate legacy `user_usr`, `userconfig_ucfg`, custom-field ACLs, portal-only modes and volunteer scopes using an effective-permission preview and rollback plan. Existing accounts are not silently assigned new roles or broadened grants.
7. **Separate assignment and editing authority:** Only authorized role/security administrators may create or assign church-owned roles. Role editing cannot be used for self-promotion or indirect escalation through group membership, imported permissions, API, plugins or user management. Require appropriate approval/step-up for high-risk changes; exact policy remains D2/D6.

**Approved product direction (George, 2026-10-10):** built-in role templates plus church-owned customization and multiple role assignments, with advanced editing optional. Newly created **staff** accounts default to read-only **ordinary** directory access unless an explicit additional role is granted; portal-only members have no staff-directory default. Detailed role grants, safe scope defaults, composition conflict resolution and migration rules are **not yet approved for implementation** and must be validated by [common-user audit #10520](https://github.com/ChurchCRM/CRM/issues/10520), [administrator audit #10519](https://github.com/ChurchCRM/CRM/issues/10519), [field audit #10515](https://github.com/ChurchCRM/CRM/issues/10515), [export audit #10516](https://github.com/ChurchCRM/CRM/issues/10516), [GHSA retrospective #10517](https://github.com/ChurchCRM/CRM/issues/10517), and [kiosk audit #10518](https://github.com/ChurchCRM/CRM/issues/10518).

### Default directory policy
- Ordinary **staff** retain broad access to **standard** people and families, including inactive records where normal staff workflow requires it. Portal-only users are **not** staff and must remain limited to their own approved relationships.
- Record lifecycle (active, inactive, deceased) and record visibility (standard, restricted, possibly protected) are independent.
- **Approved product direction:** ordinary staff see standard people/families, while selected people may be hidden/restricted. **Only a pastor or explicitly designated pastoral leadership may set or remove a restricted classification**; general staff and technical administrators cannot classify/declassify solely by virtue of their role. This is distinct from permission to view a restricted record.
- **Proposed operational default:** existing records remain `standard` on migration; restricted people are absent from ordinary directory search, direct URL, family references and exports, including existence disclosure. Restricted-record access must be separately granted to appropriate church personnel and audited.
- **Open decision D1 (remaining):** precise restricted/protected categories, who may read restricted records, how pastoral designation is established/revoked, whether any placeholder is safe, family propagation, emergency override and historical visibility migration. **Do not implement restricted visibility until these security details are approved.**

### Trusted Pastoral Leadership group — approved church workflow (P9)

A church may designate an **existing active group** as Trusted Pastoral Leadership. The group remains usable for church rosters and communication; the designation is a separate security configuration, **off by default**, never inferred from the group's name. The schema currently has `group_grp` and `person2group2role_p2g2r`; its group-local roles are not application RBAC roles.

**Mandatory authorization boundaries:**
1. Designation requires a distinct `security.trusted_group.designate` capability, step-up authentication, explicit confirmation, and an audit entry. Existing members must be reviewed and approved before any privileges activate. No automatic designation on upgrade.
2. The designated group grants only a **configured allowlist of pastoral actions**, such as `people.visibility.manage`. It never grants blanket administrator, finance, credential reset, protected-record reading, or highly confidential note access. Per-note explicit sharing remains required.
3. Adding, removing, transferring, importing or restoring a person in a trusted group requires separate `security.trusted_group.members.manage` authorization, including all UI, MVC, API, import, plugin and legacy paths. Ordinary group management, group-local leadership and ministry management do not suffice. Prevent self-addition, self-approval, indirect escalation through group merges/clones and membership edits through alternate routes.
4. Resolve the member to a currently enabled authenticated user account; group membership alone does not create an account or a grant for a portal-only account unless the approved policy explicitly allows it. Evaluate live group designation, membership, account state, action allowlist, record scope and sensitivity on every request; revoke by next request after any change.
5. Protect group deletion/deactivation, designation removal, role changes and privilege configuration with equivalent authorization. Log actor, target, reason, approval and outcome without pastoral content. Deny when group membership or authorization state cannot be verified.
6. Provide church-friendly UI labels ("Trusted Pastoral Leadership", "Who can change membership?", "Which responsibilities does this grant?") with a clear warning about security impact, rather than exposing raw IAM vocabulary.

**Open decision D7:** designate/approve authority (pastor alone or two-person approval), initial member confirmation, precise allowed pastoral actions, delegation and review cadence, whether grants are computed dynamically or synchronized to application roles. Until approved, do not wire ordinary `group_grp` membership or `p2g2r_rle_ID` directly to privileged RBAC grants.

### Church Board and Voting Members — approved church governance model (P10)

**Church Board** (elders, trustees, council or equivalent) is a church governance group, not an automatic software security role. A board member receives ordinary member/portal access unless independently assigned staff, finance or other application permissions. Board group membership alone never grants confidential pastoral, safeguarding, financial or restricted-person access. Board secretary/treasurer capabilities are separately assigned and audited.

**Voting eligibility** is a **structured person-level church membership attribute**, independent of ChurchCRM group membership, ministry, board office and software RBAC role. A voting member may have no ChurchCRM login. No automatic eligibility is inferred from attendance, age, donations, baptism, membership date, household relationship, person classification or group membership. Churches differ in bylaws and denominational rules; ChurchCRM records the church's determination rather than claiming to decide canonical eligibility.

**Recommended functional contract for review:**
- A church-authorized membership administrator can record `eligible`, `ineligible` or `undetermined` with effective date and change history. `undetermined` is the safe default; do not silently interpret it as eligible. A reason or evidence reference, if recorded, must be access-restricted and should not require sensitive pastoral details. **D8** must settle whether eligibility is current-state plus audit or an effective-dated history, and whether church-specific statuses are needed.
- Church staff with explicitly granted governance/membership access may see or update eligibility; portal-only members and ordinary volunteers must not see other people's eligibility. A person viewing their own eligibility is a separate church-configurable decision. Reports/exports and APIs require dedicated permissions, auditing and least-privilege data minimization.
- Eligibility must not be linked to RBAC `role` grants, login status or trusted pastoral-group membership. Eligibility is **not a voting/election feature** in this RFC: no ballots, proxies, secret votes, quorum computation or automated decisions about bylaws.
- On a **new install**, make the attribute available without assigning any person as eligible. Offer optional Church Board group setup; do not auto-create populated groups, trusted groups or board-specific security privileges. Keep normal onboarding usable without a governance configuration wizard.
- On **upgrade**, preserve existing person classifications, membership dates and board/voting groups unchanged. Backfill the new attribute as `undetermined` unless a separately reviewed, church-confirmed import/mapping is provided. Do not infer eligibility from `per_cls_ID`, `per_MembershipDate`, group names or group roles. Provide a report of unmapped records and a church-friendly, explicitly confirmed bulk review process. Migration must be additive, reversible and tested on existing data.
- **Verified schema baseline:** `person_per.per_MembershipDate` and `person_per.per_cls_ID` exist in installation SQL and `orm/schema.xml`; neither establishes voting eligibility. Exact storage (new person column vs separate eligibility/history table), index and Propel migration must be designed after reviewing existing membership workflows.

**Membership Date governance control (security finding, [#10512](https://github.com/ChurchCRM/CRM/issues/10512)):** Current `src/PersonEditor.php` allows ordinary `EditRecords` or `EditSelf` for self/same-family to enter the person editor, accepts `MembershipDate` in POST and calls `setMembershipDate` without a dedicated field-level authorization check in that path. This is a verified authorization-design gap, not a claim that all endpoints have been exploited. **Target policy:** editing membership dates requires separately assigned `membership.date.manage` (for example, Membership Officer), not ordinary people editing, self/family editing, board-group membership, voting status or automatic admin wildcard. Enforce on every write path including imports/APIs, preserve the original value on unauthorized edits, and audit old/new value, actor and reason. Keep this permission separate from `membership.voting_eligibility.manage`; changing the date must never automatically change eligibility. Audit legacy users and plan a safe transition before enforcement. See [docs #110](https://github.com/ChurchCRM/docs.churchcrm.io/issues/110) for release-accurate documentation.

**Verified cross-feature dependency:** The current `src/Reports/VotingMembers.php` counts people with a classification whose **name** equals translated `Member`, excludes deceased records and optionally requires family payment history; it does not evaluate Membership Date or an explicit voting-eligibility attribute. This is a legacy report heuristic, **not** approved voting policy. Before migration, audit and determine a safe transition (relabel/deprecate/rewrite with church-confirmed mapping), including report permissions and no silent output changes. The membership anniversary report reads Membership Date, and CSV import/export/search also touch the field. See [audit #10514](https://github.com/ChurchCRM/CRM/issues/10514).

**Search product dependency:** [#10513](https://github.com/ChurchCRM/CRM/issues/10513) proposes a visible Church Members filter. Existing person classification filtering is not itself a canonical membership policy. Choose a stable church-configurable membership definition, never infer from date, voting eligibility, donations or localized classification label; honor restricted-record visibility and export permissions.

**Open decision D8:** who may set/revoke eligibility, who may read/export it, whether members can see their own status, eligibility history/effective dates, reason retention, how churches configure governance rules, optional board-group defaults and safe bulk migration. Do not implement assumptions about denominational bylaws.

## 4. Authorization contract (all entrypoints)

Canonical permission keys are lowercase dot-separated, e.g. `people.read`, `people.update`, `notes.read`, `notes.update`, `notes.export`, `account.recovery_email.change`, `account.credentials.reset.other`, `plugin.<id>.use`. Keys are centrally registered, not created ad hoc by routes. Unknown actions and policy failures **deny**. A feature flag cannot grant access.

```php
interface AuthorizationService {
    public function decide(Principal $actor, string $action, ResourceContext $resource): Decision;
}
```

`Decision` contains `allowed`, machine-readable `reason`, and internal audit metadata. `Principal` contains account/service identity, authentication method, token scopes and assurance. `ResourceContext` is constructed from **server-loaded** resources, including parent person/family, classification, owner and scope; do not trust client-supplied relationship IDs.

**Effective allow requires all applicable checks:** authenticated active principal; appropriate portal/staff boundary; module enabled; action granted by a role/legacy compatibility mapping; resource in scope; record/field sensitivity permits action; credential scopes permit action; step-up authentication where required. Any deny wins. **Approved boundary:** system/church administrators may manage configuration and normal administration, but administrator status alone **never** grants access to highly confidential pastoral/safeguarding content or bypasses protected-person policy. Ordinary role wildcard semantics remain D2.

**Enforcement:** authentication first, then `RequirePermission` route middleware, then service/resource authorization and query-scoped filtering. Slim LIFO order must be explicitly integration-tested. Use the same policy in MVC, API, legacy PHP, Member Portal, plugin endpoints, background jobs, reports, exports, PDFs, search, autocomplete, counts, bulk actions, photos, attachments, calendar and related-record views. Lists must filter *before* pagination/counting. Every by-ID GET/PUT/PATCH/DELETE must check the loaded record. For hidden records, public response should not confirm existence; internal logs may record denial without sensitive content. Use 401 for unauthenticated and 403 for ordinary forbidden requests, with indistinguishable 404 where existence is protected. Authorization failures must not partially commit writes.

**Open decision D2 (remaining):** administrator wildcard for **ordinary** capabilities, role grant ceiling, built-in role immutability, break-glass workflow and last-admin safeguards. **Proposed:** built-in templates immutable and clonable; no silent self-escalation; sensitive actions never implied by `admin`.

## 5. Identity, email, member accounts and guardians

1. **Approved target:** every person has a **unique personal email address when one is recorded**; `usr_UserName` also remains unique. A person may have no email and may have no account. Do **not** interpret this as requiring a mailbox for children, visitors or people without email; whether email must be present is **not approved**. A family email remains a separate household contact destination; it is **not** an individual sign-in/recovery identity. The uniqueness scope and whether a personal email may equal the family email require review.
2. **Verified conflict:** existing `per_Email` has no uniqueness constraint, and duplicate/shared personal emails may exist in real installations. Do **not** immediately add a unique index, rewrite data, drop records, block upgrades or silently choose which spouse owns an address. Inventory duplicates, normalization/case-folding, null/empty values, historical imports and self-service behavior; provide a church-friendly remediation and phased enforcement plan for George's approval. If email is a login identifier, it must be separately verified and uniquely bound to one account; changing a contact email cannot transfer account identity.
3. Editing contact email (`people.contact.edit`) must **not** implicitly change verified login/recovery identity. Recovery destination changes need independent authorization, reauthentication/step-up, proof of control of the new destination, notice to the old destination, expiration of pending reset tokens and audit. Privileged-account changes require stronger review; token-based callers may not reset credentials by default.
4. Keep `person_per` and `user_usr` separate; self-service account setup and age/eligibility decisions from [#10464](https://github.com/ChurchCRM/CRM/pull/10464) remain unmerged and require coordination. Portal-only accounts receive no staff privileges or automatic API token.
5. **Approved product direction:** verified parents/guardians can manage **approved** child information; pickup authorization is a **separate** relationship/capability. Household membership does not prove guardianship. Emergency contacts and other household adults receive no automatic sensitive-child access. Custody disputes, blended households, consent, medical/safeguarding notes, pickup permissions, minors reaching adulthood and guardian revocation need separately reviewed workflows.
6. **Open decision D3 (remaining):** personal-email uniqueness semantics (case sensitivity, nulls, imported duplicates, family-email overlap and rollout), verified login/recovery-email storage, existing recovery endpoint trust, guardian verification and relationship schema, child-age transitions and staff-assisted recovery. **Do not build these flows from assumption.**

## 6. Notes, custom fields and sensitive data

A feature grant does not imply access to every record. Evaluate **record-specific** read/create/update/delete/share/classify/export policy and parent-record scope. Private notes currently have conflicting visibility methods; inventory callers and preserve legacy semantics until a migration decision. Avoid leaks via list/count, search snippets, related-record timelines, notifications, print, API and plugins. Audit classification, sharing, access and modifications without logging note body.

**Approved product direction:** ordinary **private notes** are readable by their author and specifically authorized staff; **highly confidential notes** are readable only by explicitly shared recipients, not by every administrator. Church administrators manage the system but receive no automatic content access to highly confidential pastoral or safeguarding information. Access to ordinary private notes and explicit sharing must be independently enforced and audited. **Open decision D4 (remaining):** which staff can be authorized and by whom; author access after role loss; legacy `nte_Private` migration; whether a new classification/ACL table is required; emergency access; and handling a note whose author is disabled/deceased. Do not silently reinterpret existing notes.

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

**Open decision D7:** who may designate a trusted pastoral group, authorize initial members and future changes; approved capability allowlist, delegation, storage, revocation and review cadence. No existing group is automatically designated during upgrade.

**Open decision D8:** governance eligibility storage, authorization, effective dates, new-install defaults and no-inference upgrade workflow. Preserve legacy membership data without automatically conferring eligibility.

**Open decision D6:** exact migration file location/numbering, initial role mappings, permission catalog completeness, compatibility sunset, performance limits and rollout switch semantics. Verify repository conventions first.

### Person/family and custom-field audit gate

Before approving role seeds, migrations or record visibility enforcement, complete [person/family field-level audit #10515](https://github.com/ChurchCRM/CRM/issues/10515). Inventory every `person_per`/`family_fam` field, custom field master/value table, family relationship, classification/property, contact, notes, membership/governance field and dependent output. Map actual read/write access for staff, self/family editing, guardians, portal, group leaders, administrators, reports, imports/exports, APIs and plugins. Include printed/mailing **labels** and cart/selection flows, distinct from classification, properties and groups.

Existing person/family custom fields use legacy `FieldSecurity` values (including `bAll`, `bFinance`, `bNotes`, `bAdmin`, custom flags). Preserve **per-field read/write security** during RBAC migration; custom field definition administration is a separate permission from reading or editing values. A record-level allow must not bypass a more restrictive field-level classification. Unknown legacy custom security must fail closed pending explicit mapping, with church-facing upgrade reporting; don't silently turn sensitive custom fields into ordinary staff-visible fields. Audit all output paths for disclosure through labels, exports, reports, search, counts and APIs. The audit is a prerequisite, not evidence that every current path is safe.

### Bulk export and printing boundary

**Verified:** `src/CSVExport.php` and `src/CSVCreateFile.php` require admin for the dedicated CSV flow, but `webpack/crm-bootstrap.js` registers global DataTables CSV/print buttons without an independent RBAC grant. The `.no-export` column selector is presentation filtering, not security. This does **not** establish that every DataTable has all records client-side or that every export endpoint is exposed; inventory and test actual data flows in [#10516](https://github.com/ChurchCRM/CRM/issues/10516).

**Proposed policy:** reading a permitted person/family/field does not imply bulk extraction. Design separate scoped capabilities for limited printing, filtered export and full/bulk export, with dataset-specific restrictions (ordinary directory, protected persons, children, custom fields, finance, pastoral/safeguarding data). No blanket admin bypass of highly confidential data. Keep useful church print/label/report workflows where appropriately scoped, but gate export generation and server data feeds—not merely UI buttons. Audit output events without recording exported contents. A browser that already received data cannot be prevented from copying it, so avoid overfetching and do not claim perfect exfiltration prevention. New-install and upgrade defaults, delegation and staged compatibility require George's approval.

### Historical security-advisory review gate

Before implementing RBAC, complete [GHSA retrospective #10517](https://github.com/ChurchCRM/CRM/issues/10517) against **all** published ChurchCRM/CRM advisories and authorized private cases without exposing embargoed information. Verify fix commits and regression tests on current main, not just advisory patched-version metadata; deduplicate overlapping reports. Historical lessons include direct unauthenticated access to person/family photo files (GHSA-r286-h493-ppwj), public API login bypassing 2FA/lockout (GHSA-8cwr-x83m-mh9x, duplicate of GHSA-472m-p3gf-46xp), OTP lockout inconsistency (GHSA-f2fq-4rmp-9x8c), destructive GET/CSRF (GHSA-jx5r-p82p-2p8m), SQL injection in family and user editors (GHSA-h3vq-9gr6-h9r4, GHSA-whpp-wx64-4qp9), stored XSS in family/pledge editors (GHSA-mrr9-jxqx-xv62, GHSA-wjmf-w8gj-rx7g), and setup/restore RCE (GHSA-m8jq-j3p9-2xf3, GHSA-mp2w-4q3r-ppx7, GHSA-r6cr-mvr9-f6wx). These are historical advisory claims, **not** proof that vulnerabilities persist in current code.

**Design invariants:** no sensitive data served via unguarded static paths; consistent authorization across UI, API, portal, files, exports, labels, plugins and background jobs; authorization for **record + field + action + scope**; no role-only assumption that makes CSRF safe; safe queries and output encoding; full 2FA/session/API-key lifecycle; fail-closed handling for unknown grants; tests for direct-ID access and alternate endpoints. Not every GHSA is an RBAC bug: installer/restore RCE and injection require separate security boundaries, even though they can expose the whole database.

### Kiosk devices as narrowly scoped non-user principals

Kiosk device authentication must **not** inherit the ordinary staff or administrator role catalog. Current `src/kiosk/routes/device.php` uses kiosk-cookie authentication and checks accepted-device/active-event assignment on many operations. Devices can check in/out, list assigned rosters, retrieve authorized roster photos/family information, trigger notifications and register guests. Roster checks exist on photo/family endpoints, but this is **not proof** that every route and every returned field has adequate scope. Complete [kiosk device security audit #10518](https://github.com/ChurchCRM/CRM/issues/10518) before implementing RBAC. Define a separate device principal and per-operation/event/class/person/field capability matrix, strict credential and assignment lifecycle, negative cross-roster tests, revocation and auditing. Provisioning/acceptance/assignment must not be silently equivalent to broad `ManageGroups` or admin grants. Preserve legitimate child pickup/check-in workflows while minimizing child and household data exposure.

### Church administrator's end-to-end product acceptance gate

The design must be usable by a church IT/office administrator who asks **who can do what, to which people/families/fields, through which channel, and who can grant or revoke it**. Complete [administrator-journey audit #10519](https://github.com/ChurchCRM/CRM/issues/10519) before approval: first install, staff onboarding, role delegation, member/guardian portal, children and protected persons, membership/governance, confidential notes/custom fields, finance, directory/report/export/print/email, kiosk and API/plugin service identities, temporary access, revocation, incident review and safe upgrade. Document as-is UI and effective permissions separately from proposed behavior. Require a simple role-template default plus optional advanced controls, effective-access preview, source-level enforcement, understandable audit trails and migration preview. Do not force churches to model governance groups as software roles or imply admin access to highly confidential content.

### Everyday church-user journey acceptance gate

Complete [common-user access/privacy audit #10520](https://github.com/ChurchCRM/CRM/issues/10520) alongside [church IT administrator audit #10519](https://github.com/ChurchCRM/CRM/issues/10519). Validate realistic tasks and expected hidden data for office staff, pastors, safeguarding leaders, finance, membership officers, ministry/team leaders, Sunday School and check-in volunteers, ordinary members, parents/guardians, visitors, board members and temporary workers. Compare **actual** UI/API/portal/export/report behavior with church-configurable target policies; include person/family/custom fields, photos, children, attendance, giving, notes, membership, communications, CSV/print and mobile. Reuse existing `VolunteerAuthorizationService` ministry/team scopes and `PortalSelfService` write allowlists where appropriate, rather than inventing unscoped roles. Require understandable denied-access UX, negative cross-scope tests, and migration that preserves authorized daily church workflows. Record unresolved privacy/governance decisions for George.

### Backward-compatible, new-feature-first adoption strategy (recommended implementation sequencing)

**Architecture objective:** deliver a reusable authorization contract for new screens and endpoints **without requiring an immediate migration of every existing ChurchCRM page, account, report or database row**. Existing authorization checks remain active until each feature is deliberately migrated and tested. This is a proposed execution strategy, not authorization to ship new policies or relax current safeguards.

| Workstream | Initial impact | May ship before full legacy migration? | Guardrail |
|---|---|---|---|
| Permission vocabulary, immutable capability identifiers and policy decision interface | Low | **Yes** | No behavior changes; explicit action/resource/scope/field context |
| Central authorization service and deny/allow result with reason codes | Low | **Yes** | Deny unauthenticated/unknown new capabilities; no implicit admin wildcard for sensitive actions |
| Tests, static inventory and decision instrumentation | Low | **Yes** | Shadow decisions must not leak data or record sensitive payloads |
| New features with feature-local capability gates and explicit scope | Low–medium | **Yes** | Gate all entrypoints, server queries and output channels; do not rely on hidden buttons |
| Reuse existing volunteer scopes and portal field allowlists via adapters | Medium | **Yes, incrementally** | Preserve their existing enforcement; do not weaken direct-ID/portal isolation |
| Read-only preview of effective access and built-in template catalog | Medium | **Yes, once semantics are verified** | Label previews as partial while legacy paths remain; no misleading promise of global enforcement |
| Church-owned role editing, persistence and role assignment | Medium–high | **Only for explicitly RBAC-enabled features initially** | Never silently grant access to legacy screens or override existing checks; approve grant ceiling and revocation semantics |
| Global row/field privacy, protected people, confidential notes, export/print policy | High | **No blanket activation** | Requires cross-channel audits and migration of all read paths that can expose the same data |
| Replacing legacy permission flags, existing user editor and all route middleware | High | **Not a prerequisite** | Stage per-feature, preserve effective grants, compare decisions and provide rollback |
| Personal-email uniqueness, guardian model, voting eligibility, legacy report rewrite | High / independent data changes | **Not a prerequisite** | Separate reviewed schema, migration, user policy and tests |

**New-feature contract:** new code calls one server-side authorization interface (illustrative `authorize(principal, action, resource, scopeContext)`) before protected reads or writes. It uses stable capabilities, an explicit principal type (staff/portal/device/service), record/field classification and scoped resource identifiers; queries and serialization enforce the same policy. Each new feature declares its capability requirements and defaults. Do not build new screens by copying legacy `isAdmin()` or module flags into new access rules. A narrowly scoped compatibility adapter may translate *existing effective* legacy permissions for an unchanged workflow, with documented mappings and tests; it must not invent sensitive grants.

**Dual-path transition:** keep existing middleware, `user_usr` flags, `userconfig_ucfg`, `FieldSecurity`, portal allowlists and volunteer scopes authoritative for **unmigrated** legacy features. New RBAC gates are authoritative for explicitly opted-in new features. During migration of a legacy feature, require **both** its existing authorization checks and the new policy unless an approved, tested cutover replaces the old checks. Do not globally change `User::canReadPerson/Family`, `isAdmin()`, user editor behavior or old report semantics as part of the foundation release. Do not apply an additive RBAC grant to bypass a legacy denial.

**Security caveat:** a new UI cannot safely claim a record is hidden, confidential or non-exportable if any legacy route, static asset, API, DataTables payload or report still exposes it. Feature-local authorization is feasible when its data/action is isolated; system-wide negative access guarantees require migrating **all** paths to that resource first. Prefer additive new data with isolated endpoints, feature flags default-off for migrations, and targeted cross-entrypoint tests. Security fixes to confirmed vulnerabilities must not wait for the RBAC redesign.

**Proposed incremental gates:**
1. **F0 — Contract:** permission catalog, typed principals, policy decision service, audit/decision tests and documentation; no schema or behavior change required for the minimal foundation.
2. **F1 — New feature pilot:** choose an isolated low-risk workflow; declare capabilities, use current verified identity/scope services, protect every endpoint and query, test denial and revocation. Do not pilot first on pastoral notes, protected people, finance, kiosk cookies or global exports.
3. **F2 — Role metadata and UI:** add versioned built-in templates, church-owned copies and assignments only after D2/D6 decisions. Initially apply them solely to RBAC-enabled features. Preview clearly identifies legacy permissions separately.
4. **F3 — Legacy feature-by-feature cutover:** inventory route/data/output coverage, shadow-compare access, preserve baseline permissions, introduce policy enforcement behind reviewed per-feature switches, validate negative tests and rollback, then retire redundant checks.
5. **F4 — Cross-cutting confidentiality and data migrations:** implement only after complete dependency audits and explicit George approvals; do not make these blockers for unrelated new features.

**Acceptance for each migration slice:** no unauthorized gain, no unapproved loss of existing workflows, direct URL/API/print/export checks, mobile/portal parity where relevant, measurable performance, audit/revocation behavior, upgrade fixtures and rollback. Release notes must say which screens enforce new roles and which still use legacy permissions; never imply global RBAC completion during transition.

### Mandatory adversarial security invariants for incremental adoption (design-review disposition)

The following are **implementation blockers for the affected phase**, derived from the adversarial review on [PR #10511](https://github.com/ChurchCRM/CRM/pull/10511). They refine the proposed F0–F4 sequence; they do **not** authorize runtime changes or assert confirmed vulnerabilities. They must be addressed before any affected new feature is considered RBAC-protected.

**S1 — Complete resource exposure boundary (High).** Define an exposure graph for each protected data classification: direct pages, APIs, alternate identifiers, joins, related records, search/autocomplete/counts, thumbnails/static files, reports, print/labels, CSV, downloads, emails, caches, integrations, jobs and plugin data paths. A *screen* is not a migration unit when its data is available elsewhere. Do not activate a confidentiality promise (e.g. restricted people, highly confidential notes, minors) until every exposure path is enforced or removed. Legacy routes must not become an alternate access path. A local route fix alone is insufficient.

**S2 — Explicit trusted per-feature authorization modes (High).** Each feature has a server-controlled mode, never chosen by a request header, body, query parameter, client flag or user-editable setting:

| Mode | Enforcement | Allowed use |
|---|---|---|
| `LEGACY` | Preserve existing effective legacy policy, including existing denies | Default for unmigrated legacy features |
| `DUAL_ENFORCE` | Both legacy and RBAC policies must allow; deny wins | Audited migration of an existing feature |
| `RBAC` | RBAC policy and all mandatory classification/credential guards must allow | New features by default; legacy only after approved parity cutover |

Unknown modes, missing policy, invalid resource, failed dependency or policy-evaluation exceptions **deny** for RBAC-enabled operations; never fall back to a permissive legacy check. Existing legacy behavior is not silently changed by adding the framework. Shadow comparisons are observational only, cannot authorize requests, and must not expose sensitive payloads. A legacy grant cannot override an RBAC denial in dual mode. Switching an existing feature to RBAC requires explicit review that historical restrictions are represented, not merely a passing allow test.

**S3 — Whole-grant evaluation and mandatory denies (High).** A grant is the inseparable tuple of action, resource type, resource scope, field constraints, principal/credential conditions and applicable expiry. For a request, **one complete grant** must cover all dimensions. Never combine an action from role A with a wider scope from role B. Multiple roles can authorize via separate complete grants, but cannot override classification denies, relationship proof, account state or explicit sharing requirements. Unknown capabilities and empty/missing scope deny for new sensitive operations. Admin wildcard cannot override highly confidential or protected-resource rules.

**S4 — Privilege-safe role administration (High).** Separate permission to define roles, change role grants, assign roles, manage trusted-group membership and authorize high-risk access. Every mutation path (UI/API/import/restore/job/plugin-controlled API) must validate the actor's *grantable* ceiling against the proposed effective privilege diff, not just the role's name. No self-promotion, self-approval or indirect elevation through group membership, templates or legacy permission adapters. Preserve last-admin recovery and require explicit step-up/approval for designated high-risk changes, with audit events excluding confidential content. Template upgrades never silently expand a church's grants.

**S5 — Revocation, caches and asynchronous execution (High).** Revocation takes effect no later than the next request for sensitive operations, including API/token-backed requests. Define a policy/assignment revision or equivalent authoritative revalidation; invalidate or avoid authorization decision caches keyed by principal, credential, policy revision, action, resource, scope and field. Re-check rights when queued work actually executes, not only when enqueued; handle signed links, kiosk sessions and device/service credentials separately. A copied export cannot be retroactively revoked: minimize data and use bounded download links. Test active sessions, stale tokens, queued jobs and concurrent role removal.

**S6 — Query, relationship and serialization boundary (High).** Enforce person/family/guardian/ministry scope and field-level classification before returning data, not merely in menus or after loading a broad collection into the response. Verify relationships independently of household labels and client IDs. Apply consistent policy to related objects, search/counts, error messages, logs, image URLs, exports and derived output. Do not leak existence of protected records through response differences where nondisclosure is required. No untrusted user-provided field list may expand a server-authorized projection.

**S7 — Feature-policy integrity and fail-closed behavior (Medium–High).** The authorization mode and sensitive feature flags must be trusted, validated server configuration, with privileged audited changes. A malformed mode, unavailable policy service, missing scope, deserialization error or cache outage cannot turn a denied RBAC operation into an allow. Fail closed for new/RBAC-sensitive features; do not introduce global fail-closed behavior to unmigrated legacy workflows without a reviewed compatibility decision. No authorization through shadow-mode results.

**S8 — Mutation-time authorization and concurrency (Medium–High).** For role grants, trusted group designation/membership, guardianship, protected classification and other high-risk writes, enforce authorization at the mutation boundary against current state. Use transactions with appropriate locking or conditional updates/version checks where state can change between check and commit. Role edits require optimistic concurrency or equivalent protection against lost updates. Tests cover revoke-versus-write, simultaneous role changes and scope reassignment.

**S9 — Honest effective-access preview (Medium).** Show the source of each grant (legacy flag, RBAC role, trusted group, service/device scope), its field/resource/channel limits and enforcement mode. Mark unconverted pages as legacy-enforced; do not claim a new role or restriction protects all screens while alternative legacy paths exist. Include print, filtered export, bulk export and derived outputs where known.

**S10 — Canonical identity and safe decision caching (Medium).** Canonicalize principal type and ID, credential identity, resource type and ID, role/policy revision and scope before evaluation. Prefer no shared decision cache in F0; if added, keys must include all authorization-relevant dimensions and avoid cross-request principal confusion. Never trust arbitrary request-supplied role, scope, identity or tenant context.

**Illustrative decision flow (not production code):**

```text
principal := authenticate_and_validate_credential(request)
resource  := resolve_canonical_resource_without_disclosing_protected_data(request)
context   := server_derived_action_resource_scope_fields_and_relationships(request)
if principal inactive OR credential insufficient OR mandatory classification denies: DENY
mode := trusted_server_feature_policy(context.feature)
if mode == LEGACY: return legacy_policy(principal, context, resource)
if mode == DUAL_ENFORCE: return legacy_policy(...) AND rbac_whole_grant_policy(...)
if mode == RBAC: return rbac_whole_grant_policy(...)
return DENY  // unknown mode, missing policy, error
```

**Cutover rule:** the mandatory classification guard must not be advertised as effective for a shared legacy resource until S1's exposure graph is closed; similarly, RBAC-only cutover must preserve all applicable historical denials. The decision flow is conceptual, not a substitute for query-layer enforcement or a guarantee that in-process plugins are isolated.

**Required security regression matrix:** same-resource access via UI, direct ID, API, list/search/count, export/print, background jobs and service credentials; role-A action + role-B scope cross-product; administrator denied highly confidential content; self-escalation by role edit/import/group change; role revocation during session and async execution; concurrent scope mutation; invalid mode/policy exception; cross-principal cache contamination; overbroad serializer fields; migration rollback without access expansion. Test both allowed church workflows and prohibited paths.

**F0 gate:** S2/S3/S7/S10 contract and tests, no behavior change for legacy routes. **F1 gate:** S1/S5/S6 for the isolated new feature plus full negative entrypoint coverage. **F2 gate:** S3/S4/S5/S8/S9 for role storage/editing/assignment. **F3 gate:** S1/S2/S5/S6/S7/S8 for each migrated resource and channel. **F4 gate:** complete exposure graphs and explicit D1/D3/D4/D6 approval before cross-cutting confidentiality policies go live. Independent security review is required at each boundary.

### Independent LLM review disposition and strengthened rollout contract (2026-10-10)

An independent architecture/product/security review recommended replacing dual enforcement with binary route cutover, eliminating trusted pastoral-group grants, deferring all custom roles until complete legacy migration, and simplifying voting eligibility to a boolean. These are **recommendations, not verified exploits or approved product reversals**. Disposition: **modify** binary-cutover, trusted-group and custom-role recommendations; **retain** three-state voting eligibility; **accept** stronger identity, revocation, server-side data exposure, plugin-boundary and accessible UX requirements. The findings do not establish a confirmed vulnerability without route/data-flow evidence.

**R1 — Migration ownership, explicit boundary and temporary intersection.** For every migrated feature, name an owner and maintain a reviewed manifest of its HTTP routes, HTTP verbs, API callers, direct-ID paths, repository/ORM/raw-SQL reads and writes, serializers, DataTables responses, print/export/report paths, background jobs, plugins and shared resources. **Route-level policy ownership is unambiguous**: each entrypoint is declared `LEGACY`, `DUAL_ENFORCE` or `RBAC` in trusted server configuration; there is no request-selected mode or silent fallback. `DUAL_ENFORCE` is a **temporary, explicitly bounded migration gate**, requiring independent legacy **AND** RBAC approval (deny wins) at the protected operation boundary, with recorded parity tests and an exit criterion; it is not permission union or indefinite duplicated business logic. If downstream legacy code bypasses that boundary, the slice is **not ready to migrate**. Binary RBAC-only cutover is permitted only after all existing denials and alternate exposures have been accounted for and tested. A binary route switch by itself does not secure shared underlying data.

**R2 — Trusted pastoral group without ordinary group-manager escalation.** Preserve approved P9 as an optional, default-off church workflow. The group designation is **security metadata**, not a blanket conversion of `group_grp` into an application role. Ordinary group-management, group imports, membership APIs, restores, merges, plugin hooks and background updates cannot activate or modify privileged effective membership without a separate authorized security operation and audit. Consider a protected approved-member binding or equivalent gate so an unreviewed ordinary roster change never grants security privileges; membership must still be active and independently verified at request time. Any synchronized grants must be atomically revoked on designation/membership removal. Initial roster review, grant ceiling, step-up, anti-self-approval and recovery remain gated on D7. Do not remove this approved feature solely because its naïve implementation would be unsafe.

**R3 — Contact identity and account recovery are distinct.** `per_Email` and `fam_Email` are contact data, not proof of ownership or authentication credentials. Editing them, including by staff, imports or portal, must **never automatically rebind** a login, recovery destination, 2FA reset or existing session. Recovery identity requires independently verified account-owned binding, explicit authenticated change workflow, re-verification and audit; password/2FA recovery must use only approved verified bindings. P4's unique personal email *when supplied* remains the target contact-data requirement, with duplicate remediation and compatibility review. A proposed `usr_Email` column is **not approved** until the actual recovery paths, schema and migration are inspected (D3).

**R4 — Revocation mechanism must be specified and tested.** F0 must define how an authorization decision observes the current user state, role assignments, trusted-group approvals and credential scopes. A per-principal authorization revision (or equivalent authoritative check) is a candidate, not a mandated global counter or mandatory re-login on every role change. On the **next sensitive request**, a revoked role/scope must deny even if the PHP session persists; invalid/disabled credentials must deny. API tokens, device sessions, queued work and bounded download links require separate lifecycle handling. Validate race behavior, cache invalidation, role updates and execution-time rechecks. Avoid assuming that a session-version comparison alone revokes independent API tokens.

**R5 — Stage role UX by actual enforcement coverage.** F0 can define versioned immutable templates and grant semantics without shipping a role editor. F1 should pilot a small, verified template set on isolated new functionality; **do not backfill five broad templates from heterogeneous legacy flags without a lossless, reviewed mapping**. F2 role metadata/church-owned copies may be developed for **RBAC-enabled features only**, but must not be generally exposed as an all-application permissions editor until effective-access preview and enforcement coverage make its claims accurate. F3 migrates existing resource/entrypoint slices; F2 advanced UI can expand as those slices become trustworthy, rather than waiting for a potentially indefinite 100% cutover. Avoid a five-template ceiling that collapses finance, pastoral care, office work and volunteer scopes. Provide accessible, localized, task-oriented preset cards, clear grant consequences, keyboard navigation, responsive layout and effective-access explanation; no dense matrix as the only interaction.

**R6 — Data and plugin boundaries are enforceable, not decorative.** Authorize at query and mutation boundaries, then emit only an allowlisted, policy-appropriate DTO/serializer projection. Audit direct record APIs, DataTables JSON, client CSV/print controls, filtered and bulk exports, reports, thumbnails and caches. Hidden columns or menu links are **not** access controls. Central ORM filters may help but are insufficient for raw SQL, direct files, alternate ORM paths or plugins. In-process PHP plugins are **trusted server code with database access** and cannot be sandboxed by route middleware; plugin HTTP endpoints and service credentials still need server-side policy checks. Do not claim system-wide confidentiality until every material data path is controlled or explicitly excluded.

**Independent-review decisions preserved:** P9 trusted pastoral-group workflow (with privileged membership gate); P10 voting eligibility remains **eligible / ineligible / undetermined** rather than boolean, with no inferred denominational rule; P11 church-owned customized templates and multiple scoped roles remain approved product direction. Administrator access to highly confidential notes is not implicit; explicit sharing and D4 still govern. These are product requirements, not assertions of currently shipped behavior.

**Additional acceptance tests:** deny when legacy allows but RBAC denies in `DUAL_ENFORCE`; deny when RBAC allows but legacy denies; deny on policy failure; prove RBAC-only cutover preserves historical denies; reject unauthorized trusted-group roster/import mutations; contact-email edits do not redirect account recovery; revoke a grant during active session and queued execution; reject overbroad DataTables JSON/export fields; distinguish preview coverage for legacy and RBAC features; verify no unauthorized privileges are inferred by role-template backfill. Each test must identify the exact entrypoint and resource scope.

### Second independent review: disposition and mandatory V1/V2 role-assignment transition (2026-10-10)

A second independent review examined application source and identified a credible **account-recovery security concern**: the linked person's editable contact email appears to be used as the account password-reset destination. This requires **private coordinated security validation/remediation immediately**, independently of RBAC. Do **not** publish exploit steps, sensitive reproduction details or a security-fix patch in this public RFC. Until remediation is verified, do not mark the risk resolved. Independently verify username enumeration and reset-token consumption semantics; address them in the security workstream. Do not infer that the suggested recovery-email column/backfill is automatically safe: an existing unverified contact address is not proof of account ownership.

**Second-review architecture disposition:**
- **Accept:** preserve existing `EditSelf`-exclusive portal semantics during migration; explicitly separate contact data and verified recovery identity; document the limits of application-level confidentiality against operators with database/backup/plugin execution control; add legacy authorization parity tests and small-church bootstrap/recovery design; make role grants and scope semantics implementable before role editor rollout; audit server-side query/serialization/exports and authorization modes.
- **Investigate rather than assume:** a `LegacyGrantProvider` / single `decide()` adapter may reduce duplicated checks, but must preserve every historical deny, avoid turning legacy permissions into globally additive privileges, and prove route/data coverage. `DUAL_ENFORCE` remains a temporary deny-wins option under S2/R1 until an evidence-backed design decision changes it; it cannot itself activate privileges absent from legacy. Authorization mode must be controlled by trusted deployment/code configuration, not an ordinary editable `SystemConfig` value. Rollback must not silently widen access.
- **Do not overclaim:** session user reloads may make role revocation effective on the next request without a revision counter **if** all principal, role and credential decisions are freshly evaluated; API keys, cached decisions and async work need separate verification. No new cache or counter is mandatory in F0 without a demonstrated need. ORM default filters and static grep allowlists are useful defense-in-depth but cannot replace complete raw SQL, plugin, file, query and output coverage.
- **Preserve approved product direction:** P9 optional protected pastoral-group designation; P10 three-state voting eligibility; P11 versioned built-in templates and church-owned copies. The second review's proposal to reduce voting eligibility to a boolean, to silently convert group memberships, or to declare all existing role grants global is **not approved**. A first schema must either represent a whole scoped grant or clearly limit v1 actions to existing independently enforced domain scopes; never pretend an unscoped role-permission junction enforces S3.

#### Explicit coexistence milestone: V1 authorization plus V2 role assignments (approved product direction)

**Trigger:** Once the new **V2 role catalog, built-in canned templates, and durable per-user role assignments** exist and the assignment UI is ready, enter a visible **role-assignment preparation phase**. This is a governance/readiness milestone, **not** a cutover. The existing **V1 legacy permission flags and behavior remain authoritative** for all unmigrated features. V2 roles may be assigned and previewed without being enforced on those V1 features; RBAC-enabled new features continue to enforce their declared policy. Do not auto-convert users to staff privileges from legacy flags.

**Mandatory persistent administrator alert:** Display a prominent, accessible, localized **action-required** banner on the admin dashboard and relevant user-management pages whenever one or more in-scope enabled user accounts lack an explicit V2 role assignment. Include the remaining count and a direct link to a filterable **Users needing V2 roles** queue. The banner **cannot be permanently dismissed, snoozed away, or hidden by acknowledgment** while the count is nonzero; it disappears automatically only when every in-scope user has at least one valid assigned V2 role. Recalculate from authoritative assignment/account state, not browser-local storage or an outdated cached count. Do not block sign-in or ordinary V1 church work because roles are missing.

**Who is in scope:** Inventory **all existing enabled human login accounts**, including read-only staff, admins, and `EditSelf`-exclusive portal-only members. Account classification and template choices must preserve current effective V1 behavior: `EditSelf` without admin is portal-only even if dormant staff flags are set; zero-flag users are not thereby portal-only. Disabled, deleted, and service/device identities must be shown separately in an explicit inventory with their disposition; they must not silently inflate or hide the active-human readiness count. Define an equivalent non-staff V2 portal role so portal-only accounts can complete assignment **without granting staff directory access**. Account-type eligibility and exemptions require explicit documented policy, not arbitrary dismissals.

**Assignment workflow:** Authorized security/user administrators can assign **one or more** V2 roles from versioned immutable built-in templates or reviewed church-owned copies. Present the person's current **V1 effective access**, proposed **V2 access**, sensitive-grant differences and **V2 enforcement coverage by feature/channel** side by side. Assignments must be explicitly confirmed, audited, privilege-ceiling checked and reversible; role presence alone must not be represented as parity or safe cutover. Warn about mismatches, especially dormant flags, finance, pastoral notes, exports, protected people, guardian/portal scope and trusted groups. A role assignment must not change V1 flags, elevate existing access, or automatically flip an authorization mode. Do not force a one-to-one mapping from heterogeneous legacy flags to canned templates; allow additional scoped roles or a reviewed custom copy where necessary.

**Progress and readiness:** Provide counts for `active human users`, `with V2 role`, `without V2 role`, `reviewed/parity-checked`, and `ready for specific feature cutover`. **Zero unassigned** removes the persistent assignment banner, but **does not imply V2 is enforced, equivalent, or ready for global activation**. Each feature still requires its own S1–S10/R1–R6 inventory, negative tests, policy approval and rollout gate. New enabled human accounts created during coexistence must be prompted for an explicit appropriate V2 role (including portal-only) at provisioning, or remain in the queue and keep the alert visible. Legacy-only installations before the trigger remain unchanged.

**Usability and recovery:** Keep the banner actionable, not an endless blocking modal; ensure screen-reader announcements, keyboard access, mobile reflow and translations. A small church must be able to assign roles one user at a time and understand why the alert persists. Define secure first-administrator and pastoral-designee bootstrap, last-admin safeguards and audited emergency recovery before privileged role administration is enforced; do not silently appoint a pastoral designee or require two-person approval at a one-person church.

**Required tests:** Upgraded `EditSelf` plus dormant staff flags stays portal-only; zero-flag staff stays read-only; a missing V2 assignment leaves V1 permissions unchanged; adding a role updates the queue and count but not legacy enforcement; removing the last V2 role restores the banner; creating a new unassigned account restores it; assigning a portal-only role does not confer staff access; disabled/service identities follow the declared inventory policy; concurrent assignment/revocation cannot leave stale counts; unauthorized role assignment fails; zero-unassigned does not authorize a global RBAC switch; banner is accessible and localized.

### Third independent review: final pre-F0 guardrails and disposition (2026-10-10)

A third independent multi-disciplinary review returned **Approve with Conditions** and largely validated the phased, backward-compatible design. The findings are design risks unless separately verified in current application code; no new exploit is asserted. The earlier suspected account-recovery flaw remains a **separate private security-remediation priority**, not a public implementation recipe or an issue considered resolved by this RFC.

**T1 — First durable grant schema must be honest about scope (mandatory before F0 schema approval).** Choose and document one of two safe contracts: **(a)** store an indivisible grant binding action, resource type, scope, field constraints and relevant principal/credential conditions; or **(b)** explicitly allow only enumerated early actions whose scope/field restrictions are already enforced by an existing domain authorization service, and document those dependencies and forbidden combinations. An unscoped `role_permission` join must **never** be interpreted as independently providing group/person/field scopes, or combine the action from one role with the scope from another. Multiple roles compose only complete grants, subject to resource denials. D2 and the minimum schema/early-action allowlist must be approved before implementing the assignment tables; a proposed `expires_at` or `version` field does not substitute for scoped authorization.

**T2 — Assignment authority is itself a protected feature (mandatory before assignment UI/API activation).** Every role assignment, role copy/edit, trusted-group approval, bulk operation, import, service/API action and revocation must authenticate the actor, enforce the actor's **grantable ceiling against the resulting effective-privilege difference**, reject self-promotion and delegated privilege laundering, validate role/scope/expiry, and atomically audit the actor, subject, before/after effective grants and outcome without copying confidential record contents. A ceiling violation, stale revision, unknown scope or authorization failure **denies**. Never trust the role editor UI or an imported assignment as the enforcement boundary. Provide negative tests for all mutation entrypoints, concurrency, privilege-laundering via multi-role composition, and trusted-group imports/restores. Define a secure first-admin bootstrap, last-admin safeguard and audited small-church recovery path before privileged assignments can take effect; step-up requirements must have a real supported mechanism or a documented fail-closed alternative.

**T3 — V1/V2 preparation UX for larger churches.** Retain the maintainer-approved **persistent, non-dismissible admin warning** until every in-scope enabled human account has an appropriate explicitly confirmed V2 role. Provide filterable queue, progress metrics and optional **bulk role suggestions** derived from observed V1 effective access, with per-user review, grant-diff preview, authorization ceiling checks, explicit confirmation and audit. Suggestions are **not** automatic assignments, and must preserve portal-only and read-only staff behavior. A `reviewed / intentionally legacy-only` status may be shown for planning and triage, but **does not count as an assigned V2 role or clear the banner**; allowing it as an exemption would contradict the approved completion rule. Completion of assignments never flips authorization modes, implies parity or blocks V1 work.

**T4 — Coexistence adapter and migration mode choice.** A unified legacy-derived policy adapter is a **preferred candidate to prototype and parity-test**, not an unreviewed replacement for S2. Compare it against bounded `DUAL_ENFORCE` (legacy AND RBAC, deny wins) and binary route cutover. The adapter must preserve historical denials, `EditSelf`-exclusive portal semantics, read-only zero-flag behavior, custom-field `FieldSecurity`, and existing domain scopes. It must not add legacy-derived grants to roles on routes that have not adopted the new policy. If `DUAL_ENFORCE` is used, require a named feature owner, short-lived exit gate and test evidence; no ordinary DB/UI runtime toggle or rollback that silently widens access. F0 does not depend on choosing a universal migration mode if its contracts remain policy-neutral, but any enforcing pilot must resolve its mode explicitly.

**T5 — F0 readiness and tests.** Before F0 schema or role-assignment implementation: record a decision on D2 grant ceilings/composition and T1 scope contract; specify assignment-mutation authorization and bootstrap (T2); establish negative/parity fixtures for legacy `EditSelf` with dormant staff flags, zero-flag staff, multi-role scope mixing, unauthorized assignment and V1/V2 non-enforcement; establish a private security-owner/remediation plan for the contact-email/recovery boundary. Additive, non-enforcing infrastructure may be developed only after those design gates are documented. Role-assignment UI, imports or any enforcement remain separately gated on passing S4/T2 tests and review. Test both fresh-install and upgraded databases, and ensure that rollback does not grant broader access.

**Disposition:** Accept whole-grant schema clarity, assignment-ceiling enforcement, bootstrap, accessible bulk suggestions and parity tests; conditionally favor a unified adapter after proof; **do not** accept a `reviewed but no role` bypass for the mandatory banner; **do not** silently change approved P9/P10/P11 requirements. No role tables, application behavior or migrations are implemented by this RFC update.

## 10. Security acceptance matrix (must become automated tests)

| Scenario | Required result |
|---|---|
| Read-only staff views standard active/inactive person | Allowed; no edit |
| Portal-only member guesses another person's numeric ID | Denied without existence leak |
| Staff guesses restricted person/family ID or reaches via search/export | Denied consistently across entrypoints |
| Authorized staff views restricted person | Only with explicit approved policy |
| Ordinary administrator tries to classify/declassify protected person | Denied unless also designated pastoral leadership |
| Ordinary group manager adds self to trusted pastoral group | Denied without separate privileged approval |
| Existing populated group becomes trusted | No group-derived grants until initial roster is explicitly reviewed and approved |
| Group membership/designation revoked | Effective group-derived grants removed by next request |
| Plugin, import, API or legacy route changes trusted membership | Same privileged checks and audit as UI |
| Trusted pastoral group member opens unshared highly confidential note | Denied |
| Newly created staff account reads ordinary directory but edits a person | Read allowed; edit denied until appropriate role assigned |
| Board member without staff role requests restricted records or finance | Denied; board group membership is not an RBAC grant |
| Voting member without account requests staff features | No login or staff grant inferred from eligibility |
| New install creates a member | Voting eligibility is undetermined, not automatically eligible |
| Upgrade has voting group and existing membership classifications | No inferred eligibility; original group/classification data unchanged |
| Unprivileged user edits/exports voting eligibility | Denied; explicit governance capability required |
| Technical administrator reads highly confidential pastoral/safeguarding content | Denied unless explicitly shared under approved policy |
| Authorized pickup adult tries to edit child's private details | Denied; pickup permission is separate from guardianship |
| User with `notes.read` guesses another author's private note | Denied unless record policy permits |
| Admin views highly confidential note | Denied unless explicitly authorized under D4 |
| Person editor changes another user's contact email | Must not change account recovery destination |
| Attempt to recover admin account after contact-email edit | Cannot redirect recovery through unverified contact edit |
| Two adults previously share a personal email in upgraded church | Migration detects conflict; no silent reassignment, account merge or broken upgrade |
| Two adults use the family email for household contact | Household communications remain separate from each adult's unique personal/login email |
| New person enters an existing person's personal email | Approved uniqueness rule enforced only after reviewed migration, with clear church-friendly error |
| Guardian accesses own child versus unrelated child | Only approved relationship/actions; no implicit household privilege |
| Volunteer accesses assigned roster versus another ministry | Scope enforced on list and by-ID |
| Read-only token calls write/export/recovery endpoint | Denied |
| Plugin user opens admin configuration URL | Denied; plugin runtime trust limitations documented |
| Role revoked during active session | Next request reflects revocation |
| Fresh install versus upgraded church | Same intended effective authorization and seeded templates |

Also test cross-family links, hidden-person count/thumbnail leakage, child becomes adult, 2FA reset, password recovery races, duplicate email, inactive/disabled account, bulk operations, reports, plugin SQL trust boundary, and negative authorization tests on every HTTP verb. Unit-test policy with fake principals; integration-test route middleware and SQL filtering; Cypress/Playwright test representative member/staff workflows. Do not mark these passing until evidence exists.

## 11. Review gates and ownership

**Gate A — design approval (this PR):** reviewer checks consistency, threat model, role UX, verifies the approved decisions below, and resolves the remaining parts of D1–D6 with George. No production implementation from an unresolved security decision.

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
- Can a group manager, importer or plugin promote themselves via trusted-group membership?
- Are governance membership and voting eligibility kept separate from staff security roles and board group membership?
- Does migration avoid guessing voting eligibility from existing classifications or group names?
- Have legacy Voting Members reports, membership anniversary reports, CSV/import and search been audited for field-level authorization and policy drift?
- Can EditSelf, family editing, EditRecords, imports or API calls change Membership Date without the dedicated permission?
- Are D1–D8 answered before the corresponding phase starts?
- Are built-in templates versioned and immutable, and are church customizations upgrade-safe?
- Does role composition preserve each grant's original scope without cross-product privilege escalation?
- Does the effective-access preview include print, filtered export, bulk export and sensitive field access?
- Are negative tests and rollback criteria implementation-ready?

## 12. Source issues and design dependencies

[#8591](https://github.com/ChurchCRM/CRM/issues/8591) parent; [#8758](https://github.com/ChurchCRM/CRM/issues/8758) schema; [#8759](https://github.com/ChurchCRM/CRM/issues/8759) middleware; [#8760](https://github.com/ChurchCRM/CRM/issues/8760) role UI; [#8761](https://github.com/ChurchCRM/CRM/issues/8761) menu/inline migration; [#8762](https://github.com/ChurchCRM/CRM/issues/8762) scopes; [#10464](https://github.com/ChurchCRM/CRM/pull/10464) member sign-in proposal; [#9003](https://github.com/ChurchCRM/CRM/issues/9003) staff read; [#9036](https://github.com/ChurchCRM/CRM/issues/9036) notes policy; [#8999](https://github.com/ChurchCRM/CRM/issues/8999) note IDOR; [#1886](https://github.com/ChurchCRM/CRM/issues/1886) custom fields; [#10213](https://github.com/ChurchCRM/CRM/issues/10213) email grant; [#10043](https://github.com/ChurchCRM/CRM/issues/10043) API tokens; [#10191](https://github.com/ChurchCRM/CRM/issues/10191) plugin email; [#10252](https://github.com/ChurchCRM/CRM/issues/10252) reports; [#10206](https://github.com/ChurchCRM/CRM/issues/10206) authorization gaps; [#769](https://github.com/ChurchCRM/CRM/issues/769) data-layer ACL.

**Review status:** proposal only. D1–D8 are explicitly open, not hidden assumptions. No runtime implementation or security remediation is claimed.
