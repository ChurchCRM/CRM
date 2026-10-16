---
title: "Security Advisory Review Process"
intent: "How to access, analyze, and respond to GitHub security advisories for ChurchCRM"
tags: ["security", "audit", "github"]
prereqs: ["[[security-best-practices]]", "[[github-interaction]]"]
complexity: "advanced"
---
# Security Advisory Review Process

## Audit and prioritize

1. List advisories at https://github.com/ChurchCRM/CRM/security/advisories (filter by triage, draft, published). Note count and severity.
2. Sort each by fix size:
   - **One-line** (output escaping, config validation): fix now
   - **Moderate** (add middleware, rework an endpoint): plan for the next feature release
   - **Large rewrite** (procedural pre-ORM code): open a `modernize: {feature} to use {pattern}` issue that references the GHSA ID, and fix every similar issue in that one PR
3. Prioritize by risk:
   - Critical/High (external attacker): fix before the next release
   - Medium (needs user interaction): fix in the current cycle
   - Low (admin-only): batch with the modernization issue
4. Batch similar fixes (escaping, validation). One batch is one branch, one commit, one PR.

## Read an unpublished advisory

Draft advisories are not visible in the web UI. Use `gh`:

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28"
```

The version header is required for advisory endpoints.

| Field | Purpose |
|-------|---------|
| `summary` / `description` | Vulnerability overview |
| `severity`, `cvss.score` | Rating (0-10; 7.0 and up is serious) |
| `cwes[]` | Weakness IDs |
| `state` | `draft` (private), `published` (public), `closed` (rejected or superseded) |
| `vulnerabilities[].vulnerable_version_range` / `patched_versions` | Affected and fixed versions |

### Investigation

1. Find the root cause from linked issues, CVE details, or the CWE description
2. Search the codebase for the vulnerable function or pattern
3. Check `git log` for related commits and for a reverted or incomplete earlier fix

## Fix

Branch from `master`: `security/fix-{GHSA_ID}-{VERSION}` (for example `security/fix-api-2fa-bypass-7.8.0`).

1. Restore hardened code from git history when a fix was reverted
2. Update `@OA\*` annotations when endpoints change
3. Write tests for every threat vector: the working path, failure cases with correct status codes, generic error messages (no user or email enumeration), and enforcement of each restriction
4. Run the test suite and test locally before pushing

Commit subject: `security: fix {GHSA_ID} - {short description}`. The body states the root cause and the fix. Commit and push gates follow `git-workflow.md`.

## Lifecycle

1. Fix merged to `master`. Commits are public. The advisory metadata (description, severity, CVE details) stays private until release.
2. Keep the advisory in `draft`, set the patched version to the next unreleased version, and request a CVE ID if none exists. Users get time to upgrade before disclosure.
3. On release day the advisory moves to `published`. GitHub sends security alerts and admins see upgrade notices.

## Severity: admin-only vulnerabilities

Rate by practical risk. A flaw only authenticated admins can exploit is **Low**: admins already have broad database access, so it adds no external attack surface. Record the rationale in the advisory:

```
Marked as Low severity: Only authenticated admins can exploit this
(admin-only fundraiser functions). Admins already have broad database
access, so this adds no new external attack surface.
```

Admin-only examples: CSRF on admin forms, IDOR in admin editors, session fixation on admin login.

Rate High when the flaw is reachable by non-admin users, escalates user to admin, bypasses auth on a public feature, or is stored XSS affecting any authenticated user.

Full policy: `SECURITY.md` § "Severity Assessment".

## Practices

- Use generic error messages in authentication and authorization code
- Document the root cause in the commit message
- Keep OpenAPI docs in step with endpoint behavior
- Cover "obvious" security code with tests
- Run the full test suite before merging

## Related

- [Authorization & Security](./authorization-security.md)
- [Security Best Practices](./security-best-practices.md)
- [Cypress Testing](./cypress-testing.md)
- [API Development](./api-development.md)
- [GitHub REST API: Repository Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
