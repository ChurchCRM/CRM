# SECURITY_AUDIT_FINDINGS.md

**Status:** Preliminary, source-based review only — **not** a completed end-to-end penetration test or a remediation certification. 2026-10-10. Target: ChurchCRM/CRM authorization, person/family access, portal self-service, volunteer scopes and notes; cross-cutting routes, jobs and exports remain to be inventoried. **Do not publish detailed reproduction steps or sensitive exploit data in this public repository.** Report confirmed exploitable issues through GitHub private security advisories.

## Method and limitations

Reviewed current repository source in `src/ChurchCRM/model/ChurchCRM/User.php`, `src/ChurchCRM/Slim/Middleware/Api/FamilyReadMiddleware.php`, `src/ChurchCRM/Volunteer/Service/VolunteerAuthorizationService.php`, `src/ChurchCRM/Portal/PortalSelfService.php`, and `src/api/routes/people/notes.php`. No authenticated live attack simulation, database fixture, full route/job inventory, CI execution or end-to-end exploit validation has been performed. Ratings below are **provisional risk estimates, not verified CVSS scores**. Preserve current security checks during any phased RBAC rollout; see RFC PR #10511.

## F-01 — Broad default people/family read authorization (provisional High for confidentiality; policy/design gap)

**Location:** `src/ChurchCRM/model/ChurchCRM/User.php`, `canReadPerson()` and `canReadFamily()` (locate exact lines in current source before implementation); `src/ChurchCRM/Slim/Middleware/Api/FamilyReadMiddleware.php`, `postEntityLoad()` (around lines 61–69).

**Observed:** Read-default semantics for authenticated non-EditSelf staff intentionally allow ordinary directory records. The family read middleware delegates to a read hook without row-level confidentiality. This is **not a confirmed bypass of the existing product policy**; it becomes a serious exposure risk if new features assume the hook already protects restricted records.

**Safe abuse-case validation:** In isolated test fixtures, compare staff access to an ordinary household and a separately marked protected household through direct view, API, search, reports and exports. Expect no protected-record exposure after the new privacy policy is activated.

**Impact:** Potential unauthorized disclosure of protected people/families if new confidentiality features rely on current broad read hooks.

**Recommended code contract (illustrative, not a drop-in patch):**

```php
// New protected-resource feature only; do not change legacy directory policy globally.
if (!$authorization->allows($principal, 'people.read', $person, $scopeContext)) {
    return $responseFactory->notFound(); // no existence disclosure
}
// Apply identical row/field policy in the query and serializer.
```

**Fix gate:** Inventory all alternative data paths, define D1 classification policy, add negative cross-route tests, then migrate *all* paths for the protected data atomically. A local middleware-only patch would not establish confidentiality.

## F-02 — Administrator access to private notes differs from proposed confidentiality model (provisional High for highly confidential records; future-policy mismatch)

**Location:** `src/api/routes/people/notes.php`, GET notes route comments around lines 48–54 and 179–185, update around 355–366, delete around 403–408; `User.php` private-note checks.

**Observed:** Existing routes intentionally grant admins broad private-note handling. RFC P2/P6 requires a separate explicit-sharing model for *future highly confidential* content. This is a **documented incompatibility, not proof of a current vulnerability**.

**Safe abuse-case validation:** Add a confidential-note fixture and verify that a technical admin lacking explicit recipient access cannot read, edit, delete, enumerate or export it, while authorized pastoral recipients can perform only allowed operations.

**Impact:** Confidential pastoral/safeguarding disclosure if a new note classification reuses legacy admin bypass semantics.

**Recommended code contract (illustrative):**

```php
// For new highly confidential note types only, after a reviewed schema/policy.
if (!$notePolicy->canRead($principal, $note)) {
    return $responseFactory->notFound();
}
if (!$notePolicy->canEdit($principal, $note)) {
    return $responseFactory->forbidden();
}
```

**Fix gate:** D4 recipient/sharing design, legacy note migration, all caller audit, no accidental reinterpretation of existing notes.

## F-03 — Cross-feature authorization semantics may drift (provisional Medium; architectural risk)

**Locations:** `src/ChurchCRM/Volunteer/Service/VolunteerAuthorizationService.php` (scope-specific operations); `src/ChurchCRM/Portal/PortalSelfService.php` (allowlisted person/family writes); `src/ChurchCRM/model/ChurchCRM/User.php` (legacy flags); `src/api/routes/people/notes.php` (route-level policies).

**Observed:** Distinct subsystems use different, intentional policy models. New code that checks only a generic staff permission rather than the relevant scope/record/field policy can create function-level authorization inconsistencies. **No specific bypass is confirmed by this limited review.**

**Safe abuse-case validation:** Compare each authorized workflow's direct endpoint, related-object endpoint, list, update and output behavior using users from two unrelated families/ministries and revoked-role fixtures.

**Impact:** Potential cross-household or cross-ministry access if a future endpoint omits scope checks.

**Recommended code contract (illustrative):**

```php
// A permission grant is necessary but not sufficient; preserve the original scope.
if (!$authorization->allows($principal, 'volunteer.assignment.update', $assignment, $context)
    || !$volunteerAuthorization->canManageAssignment($user, $assignment)) {
    return $responseFactory->forbidden();
}
```

**Fix gate:** Introduce a non-behavior-changing policy interface first; new features use it alongside existing scope checks. Validate every route and serializer before replacing any legacy checks.

## Outstanding audit coverage (not yet findings)

- Full API, legacy PHP page, route middleware, background-job, plugin and import/export inventory.
- Authentication/session/token issuance, replay and revocation; kiosk cookies and device lifecycle.
- SQL, HTML/JS, template and shell injection review with taint tracing.
- Parameter pollution, exceptional conditions, race/TOCTOU and concurrent assignment testing.
- Realistic role/guardian/finance/child fixtures, negative Cypress/API tests and CI validation.
- Confirmed vulnerability triage through private advisory rather than publishing exploit instructions.

## Remediation sequence

1. Complete attack-surface and data-flow inventory with line-precise evidence; distinguish existing intentional behavior from unauthorized behavior.
2. Add targeted negative regression tests for confirmed weaknesses before patching.
3. Apply **small, separately reviewed** security fixes only after identifying the actual enforcement point and verifying no alternate bypass paths.
4. Keep the new RBAC foundation backward-compatible, without claiming that unmigrated pages honor new roles.
5. Require independent review, CI, upgrade fixtures and George's approval before merge.

**This report is preliminary. No runtime fixes are included in this branch yet.**
