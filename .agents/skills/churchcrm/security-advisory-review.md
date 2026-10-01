---
title: "Security Advisory Review Process"
intent: "How to access, analyze, and respond to GitHub security advisories for ChurchCRM"
tags: ["security", "audit", "github"]
prereqs: ["[[security-best-practices]]", "[[github-interaction]]"]
complexity: "advanced"
---
# Security Advisory Review Process

This skill documents how to access, analyze, and respond to GitHub security advisories for ChurchCRM.

## Advisory Audit & Prioritization Workflow

When tasked with reviewing multiple GHSA advisories:

### 1. Gather All Advisories
Visit: https://github.com/ChurchCRM/CRM/security/advisories
- Filter by state (triage, draft, published)
- Note count and severity distribution

### 2. Categorize by Fix Complexity
For each advisory:
- **1-line fix** (output escaping, config validation) — fix immediately
- **Moderate fix** (add middleware, refactor endpoint) — plan for feature release
- **Heavy refactor** (modernize legacy code) — defer to modernization ticket

### 3. Prioritize by Risk
- **Critical/High severity** (external attack) — fix before next release
- **Medium severity** (requires user interaction) — fix in current cycle
- **Low severity** (admin-only) — defer to modernization ticket

### 4. Batch Fixes by Category
- Group similar fixes (output escaping, input validation, etc.)
- Create branches for each batch
- Each batch = one commit + one PR

## Accessing Unpublished Advisories

### Via GitHub CLI (Recommended)

Unpublished (draft) advisories are NOT accessible via the web UI. Use `gh` CLI instead:

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28"
```

### Required Headers

- `X-GitHub-Api-Version: 2022-11-28` — mandatory for security advisory endpoints
- Standard GitHub authentication via `gh` CLI or `GITHUB_TOKEN`

### Example Response Structure

```json
{
  "ghsa_id": "GHSA-cwp8-rm8g-q5c9",
  "cve_id": null,
  "summary": "Incomplete API authentication hardening",
  "description": "...",
  "vulnerabilities": [
    {
      "package": {"ecosystem": "composer", "name": "..."},
      "vulnerable_version_range": "...",
      "patched_versions": "...",
      "vulnerable_functions": ["..."]
    }
  ],
  "severity": "high",
  "cvss": {
    "vector_string": "...",
    "score": 7.5
  },
  "cwes": [...],
  "identifiers": [...],
  "state": "draft",  // or "published"
  "created_at": "...",
  "updated_at": "...",
  "published_at": null,
  "closed_at": null
}
```

## Analyzing an Advisory

### Key Fields to Review

| Field | Purpose |
|-------|---------|
| `summary` / `description` | High-level vulnerability overview |
| `severity` | CVSS severity rating (critical, high, medium, low) |
| `cvss.score` | Numeric severity (0–10, ≥7.0 is serious) |
| `cwes[]` | Common Weakness Enumeration IDs (CWE-xxx) |
| `state` | `draft` (private) or `published` (public) |
| `vulnerabilities[].vulnerable_version_range` | Affected versions (e.g., "< 7.3.1") |
| `vulnerabilities[].patched_versions` | Fixed versions |

### Investigation Steps

1. **Understand the root cause** — Review linked issues, CVE details, or CWE description
2. **Identify affected code paths** — Search codebase for vulnerable functions or patterns
3. **Verify with git history** — Use `git log` to find related commits
4. **Check for regressions** — Did a prior fix get reverted or incomplete?

## Creating a Fix

### Branching

Create a branch named after the advisory and target version:

```bash
git checkout master
git pull origin master
git checkout -b security/fix-{GHSA_ID}-{VERSION}

# Example:
git checkout -b security/fix-api-2fa-bypass-7.3.1
```

### Code Changes

1. **Restore hardened code** — If the fix was reverted, restore from git history
2. **Update OpenAPI docs** — Add/update `@OA\*` annotations if endpoints changed
3. **Write tests** — Cover all security vectors (e.g., lockout, 2FA, user enumeration prevention)
4. **Run test suite** — Ensure no regressions

### Test Coverage Requirements

For security fixes, tests should verify all threat vectors:
- Happy path (feature still works)
- Failure cases with correct error codes
- Generic error messages (no information leakage)
- Enforcement of restrictions
- Write tests covering the specific vulnerability

## Committing the Fix

### Commit Message Format

```
security: fix {GHSA_ID} — {short description}

Brief explanation of the vulnerability and fix.

Co-Authored-By: Claude Haiku 4.5 <noreply@anthropic.com>
```

## Merging to Master

Security fixes commit directly to `master` via `git merge` (no separate PR needed):

```bash
git checkout master
git merge --no-ff security/fix-{GHSA_ID} -m "merge: security fix {GHSA_ID} — {description}"
git push origin master
```

**Note:** Git commits are publicly visible, which is fine. What stays private until release is the advisory metadata (description, severity, CVE details).

## Advisory Lifecycle: From Fix to Publication

### Step 1: Fix Merged to Master
Once the fix is committed and merged to `master`:

### Step 2: Mark Advisory as Draft with Patched Version
**DO NOT publish yet.** Instead:
1. Set advisory **state** to `draft` (keep private)
2. Set **patched version** to the next unreleased version (e.g., 7.8.0)
3. Request CVE ID if not already assigned

This ensures users have time to upgrade before the vulnerability is publicly disclosed.

### Step 3: Advisory Stays Private Until Release
- Advisory remains **draft** on the repository
- Vulnerability details are not visible to the public
- Only ChurchCRM maintainers and GitHub can see it

### Step 4: Publish on Release Day
When the patched version is released:
- Advisory transitions to **published** state
- GitHub sends security alerts to watching repositories
- Admins see upgrade notifications in-app

## Severity Assessment: Admin-Only Vulnerabilities

When analyzing an advisory, consider **practical risk**, not just technical exploitability.

### Admin-Only = Low Severity

If a vulnerability can **only be exploited by authenticated admins**:
- Admins already have broad database access
- They can modify records, export data, change settings
- A vulnerability adds no new external attack surface
- Risk is insider abuse, not external compromise

**Mark as Low severity** with this rationale in comments:
```
Marked as Low severity: Only authenticated admins can exploit this 
(admin-only fundraiser functions). Admins already have broad database 
access, so this adds no new external attack surface.
```

### Examples of Admin-Only Issues
- CSRF on admin-only forms (admins can already modify that data)
- IDOR in admin editors (admins can already view any record)
- Session fixation on admin login (admins already have system access)

### NOT Admin-Only (Still High Risk)
- Vulnerabilities accessible to non-admin users
- Privilege escalation from user → admin
- Public-facing features with auth bypass
- Stored XSS affecting any authenticated user

See `SECURITY.md` § "Severity Assessment" for full policy.

## Best Practices

✅ **DO:**
- Test security fixes locally before pushing
- Use generic error messages in authentication/authorization code
- Include comprehensive test coverage (all threat vectors)
- Document the root cause in commit messages
- Update OpenAPI/API docs when endpoint behavior changes
- Create a skill/wiki entry to prevent future regressions

## Modernization Strategy for Legacy Code Issues

When a vulnerability is in legacy code and the fix would require substantial refactoring:

### Decision Criteria
- Is the affected code pre-ORM (legacy PHP procedural code)?
- Would a proper fix require moving to Slim/ORM patterns?
- Is the risk low (e.g., admin-only) or moderate (user-facing)?

### Strategy: Defer to Modernization Ticket
Instead of patching legacy code:

1. **Create a GitHub Issue** titled: `modernize: {feature} to use {pattern}`
   - Describe what needs updating and why
   - Reference the GHSA ID
   
2. **Why This Works:**
   - Avoids patching legacy code multiple times
   - Fixes all similar issues in one modernization PR
   - Improves code quality beyond the security issue
   - Clear scope for the maintainer

❌ **DON'T:**
- Commit security fixes directly to `master` without review
- Merge without running full test suite
- Reveal whether a username/email exists in error messages
- Skip test coverage for "obvious" security code
- Leave OpenAPI docs out of sync with code
- Patch legacy code when a modernization approach would be cleaner

## Related Skills

- [Authorization & Security](./authorization-security.md) — Permission checks, secure patterns
- [Security Best Practices](./security-best-practices.md) — Output escaping, CSP, sensitive operations
- [Cypress Testing](./cypress-testing.md) — E2E test patterns
- [API Development](./api-development.md) — OpenAPI annotations

## Advisory States

- `draft` — private, not public; fix merged but not yet released
- `published` — live public advisory; GitHub sends security alerts
- `closed` — advisory rejected or superseded

## References

- [GitHub Security Advisories](https://github.blog/2022-12-15-security-advisories-github-secret-scanning-and-codeql-are-generally-available/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE/CVSS Scoring](https://nvd.nist.gov/vuln/detail/CVE-2026-40582) (example CVE)
- [GitHub REST API: Repository Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
