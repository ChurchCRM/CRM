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
- Group similar fixes (e.g., all escapeHtml → escapeAttribute replacements)
- Create branches for each batch
- Each batch = one commit + one PR

### Example Audit Results (7.8.0 cycle)

| Advisory | Severity | Type | Action |
|----------|----------|------|--------|
| GHSA-p6xx-xx98-f323 | High | Custom field filtering | ✅ Fixed, merged, draft v7.8.0 |
| GHSA-vmh4-p9q5-cc9q | Medium | XSS pattern fix | ✅ Fixed, merged, draft v7.8.0 |
| GHSA-hrfr-xg9w-hjm4 | Medium | SSRF validation | ✅ Fixed, merged, draft v7.8.0 |
| GHSA-68xh-3jh8-3wvq | Low | CSRF (admin-only) | 📋 Modernization ticket #10138 |
| GHSA-9wm3-73f3-j8hf | Medium | Calendar UX | ✅ Already fixed in 7.6.1 |
| GHSA-f23p-c43w-x76w | Medium | Calendar filtering | ✅ Already fixed in 7.6.1 |

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
3. **Verify with git history** — Use `git log --all -S "pattern"` to find related commits
4. **Check for regressions** — Did a prior fix get reverted or incomplete?

### Example: Analyzing GHSA-cwp8-rm8g-q5c9

This advisory reported incomplete 2FA/lockout hardening in the API login endpoint:

```bash
# 1. Fetch the draft advisory
gh api repos/ChurchCRM/CRM/security-advisories/GHSA-cwp8-rm8g-q5c9 \
  --header "X-GitHub-Api-Version:2022-11-28" | jq '.description'

# 2. Find the vulnerable endpoint
grep -r "userLogin\|/api/public/user/login" src/

# 3. Check git history for related fixes
git log --oneline --all | grep -i "2fa\|lockout\|authentication"

# 4. Inspect the problematic commit
git show {commit-sha} -- src/api/routes/public/public-user.php
```

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

For authentication/authorization fixes, tests should verify:

- ✅ Successful authentication (happy path)
- ✅ Failed authentication with correct error codes (401, 403, etc.)
- ✅ Generic error messages (no username enumeration)
- ✅ Account lockout enforcement
- ✅ 2FA/MFA enforcement (if applicable)
- ✅ Rate limiting (if applicable)

### Example: Testing API Login Hardening

```javascript
// Test basic auth
cy.apiRequest({
  method: "POST",
  url: "/api/public/user/login",
  body: { userName: "admin", password: "changeme" }
}).then((resp) => {
  expect(resp.status).to.eq(200);
  expect(resp.body).to.have.property('apiKey');
});

// Test invalid credentials (generic error)
cy.apiRequest({
  method: "POST",
  url: "/api/public/user/login",
  body: { userName: "nonexistent", password: "anything" },
  failOnStatusCode: false
}).then((resp) => {
  expect(resp.status).to.eq(401);
  expect(resp.body).to.have.property('error');
  // Should NOT distinguish between "user not found" and "wrong password"
});

// Test 2FA requirement (202 response)
cy.apiRequest({
  method: "POST",
  url: "/api/public/user/login",
  body: { userName: "2fa_user", password: "correct" },
  failOnStatusCode: false
}).then((resp) => {
  expect(resp.status).to.eq(202);
  expect(resp.body).to.have.property('requiresOTP');
});
```

## Committing the Fix

### Commit Message Format

```
security: fix {GHSA_ID} — {short description}

- Root cause: {what was vulnerable}
- Fix: {changes made}
- Tests: {coverage added}
- Affects: {version range}

Resolves GHSA-{GHSA_ID}
Co-Authored-By: Claude Haiku 4.5 <noreply@anthropic.com>
```

### Example

```
security: fix GHSA-cwp8-rm8g-q5c9 — restore 2FA and lockout checks in API login

- Root cause: 2FA validation and account lockout checks were incomplete
- Fix: Restored hardening logic from commit 214694eb83
  - Check account lockout before password validation
  - Increment failed login counter on invalid attempts
  - Enforce 2FA OTP/recovery code validation
  - Return 202 when 2FA required but OTP not provided
  - Use generic error messages to prevent username enumeration
- Tests: 8 comprehensive tests covering basic auth, password reset
- Affects: versions < 7.3.1

Resolves GHSA-cwp8-rm8g-q5c9
```

## Creating a Pull Request

### PR Title & Description

```markdown
## Security: Fix GHSA-cwp8-rm8g-q5c9 — incomplete API authentication hardening

### Summary
Restores 2FA and account lockout enforcement in the public API login endpoint. 
Advisory reported incomplete hardening that could allow 2FA bypass or brute-force attacks.

### Changes
- Restored hardening logic from prior commit that was accidentally incomplete
- Added 2FA OTP/recovery code validation
- Added account lockout checks before password validation
- Updated OpenAPI specs to document 202 response and OTP parameter

### Testing
- 8 E2E tests passing (basic auth, password reset, error handling)
- Tests verify lockout enforcement, 2FA requirement, generic error messages
- Run locally: `npx cypress run --spec cypress/e2e/api/public/public.user.spec.js`

### Checklist
- [x] Tests passing locally
- [x] Build/lint passing
- [x] OpenAPI specs regenerated
- [x] No regressions in other endpoints

Fixes #XXXX (link to issue if exists)
Tags: security, 7.3.1
```

### Push & Create PR

```bash
# Run final validation
npm run lint
npm run build:webpack

# Push the branch
git push -u origin security/fix-api-2fa-bypass-7.3.1

# Create PR via gh CLI
gh pr create \
  --title "security: fix GHSA-cwp8-rm8g-q5c9 — restore 2FA and lockout checks" \
  --body "$(cat pr-body.md)" \
  --label "security" \
  --label "7.3.1"
```

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
When the patched version (7.8.0) is released:
- Advisory transitions to **published** state (may auto-publish or require manual update)
- GitHub sends security alerts to all repositories watching ChurchCRM
- Admins see upgrade notifications in-app

### Example: GHSA-vmh4-p9q5-cc9q (escapeHtml XSS)
```
Merged: 2026-09-28
Advisory state: Draft
Patched version: 7.8.0 (unreleased)
CVE: Requested, pending assignment
Publication: When 7.8.0 releases (TBD)
```

### Publishing via GitHub UI
Navigate to: https://github.com/ChurchCRM/CRM/security/advisories/{GHSA_ID}
1. Verify **Patched version** matches the released version
2. Verify **State** shows `Published`
3. If still in Draft, manually transition to Published

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
   - Description: What needs updating and why
   - Reference the GHSA ID
   - Label: `modernization`, `security`
   
2. **Example Issue:**
   ```
   Title: modernize: fundraiser routes to use CSRF middleware
   
   Body:
   Migrate legacy fundraiser routes (donated-items, donors, paddle-numbers) 
   to use Slim's built-in CSRF protection instead of manual validation.
   
   Currently lacking CSRF token checks. While impact is limited to 
   authenticated admins only, modernizing to framework patterns would 
   provide consistent protection and improve maintainability.
   
   Routes affected:
   - src/fundraiser/routes/donated-item.php
   - src/fundraiser/routes/donors.php
   - src/fundraiser/routes/paddle-num.php
   - src/fundraiser/routes/batch-winner.php
   
   Relates to GHSA-68xh-3jh8-3wvq (admin-only CSRF)
   ```

3. **Why This Works:**
   - Avoids patching legacy code multiple times
   - Fixes all similar issues in one modernization PR
   - Improves code quality beyond just the security issue
   - Clear scope and timeline for the maintainer

### Examples
- GHSA-68xh-3jh8-3wvq (CSRF donations) → #10138 (fundraiser modernization)
- GHSA-nnnn-xxxx-xxxx (IDOR in legacy editor) → #10136 (WhyCame/WhyLeft modernization)

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

## Updating Private Advisories (API Method) <!-- learned: 2026-04-30 -->

When a fix is merged and you need to mark a draft advisory ready for a specific version:

### Fetch Current Advisory State

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28" | jq '.vulnerabilities'
```

### Update Patched Version

The API requires the full `vulnerabilities` array. Use a JSON file to avoid escaping issues:

```bash
# Create update payload
cat > /tmp/advisory-patch.json <<'EOF'
{
  "vulnerabilities": [
    {
      "package": {
        "ecosystem": "composer",
        "name": "ChurchCRM/CRM"
      },
      "vulnerable_version_range": ">= 7.2.0, <= 7.2.2",
      "patched_versions": "7.3.1",
      "vulnerable_functions": []
    }
  ]
}
EOF

# Submit via PATCH
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  -X PATCH \
  --input /tmp/advisory-patch.json \
  --header "X-GitHub-Api-Version:2022-11-28"
```

**Note:** The `-f field=value` syntax treats arrays as strings and fails with HTTP 422. Always use `--input` for complex payloads.

### Key Field Format Rules

- `vulnerable_version_range`: Use inclusive ranges like `">= 7.2.0, <= 7.2.2"` or `"< 7.3.1"`
- `patched_versions`: Exact version string (e.g., `"7.3.1"`) or empty string `""` if unfixed
- Both fields are case-sensitive and version-literal — no wildcards

### Advisory States

- `triage` — draft, not public; fix may or may not be merged
- `published` — live public advisory; GitHub sends security alerts to affected repos
- `closed` — advisory rejected or superseded

## References

- [GitHub Security Advisories](https://github.blog/2022-12-15-security-advisories-github-secret-scanning-and-codeql-are-generally-available/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE/CVSS Scoring](https://nvd.nist.gov/vuln/detail/CVE-2026-40582) (example CVE)
- [GitHub REST API: Repository Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
