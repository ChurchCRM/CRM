# Security Advisory Review Process

This skill documents how to access, analyze, and respond to GitHub security advisories for ChurchCRM.

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

## Publishing the Advisory

After merging the fix:

1. **Wait for version release** — Advisory should be published with the patched version (7.3.1)
2. **Update CVSS/CVE details** — Add final severity, CVE ID, etc.
3. **Publish via GitHub UI** — Navigate to Security → Advisories → Draft → Publish
4. **Or use CLI** (if supported):
   ```bash
   gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
     -X PATCH -f state=published
   ```

## Best Practices

✅ **DO:**
- Test security fixes locally before pushing
- Use generic error messages in authentication/authorization code
- Include comprehensive test coverage (all threat vectors)
- Document the root cause in commit messages
- Update OpenAPI/API docs when endpoint behavior changes
- Create a skill/wiki entry to prevent future regressions

❌ **DON'T:**
- Commit security fixes directly to `master` without review
- Merge without running full test suite
- Reveal whether a username/email exists in error messages
- Skip test coverage for "obvious" security code
- Leave OpenAPI docs out of sync with code

## Related Skills

- [Authorization & Security](./authorization-security.md) — Permission checks, secure patterns
- [Security Best Practices](./security-best-practices.md) — Output escaping, CSP, sensitive operations
- [Cypress Testing](./cypress-testing.md) — E2E test patterns
- [API Development](./api-development.md) — OpenAPI annotations

## References

- [GitHub Security Advisories](https://github.blog/2022-12-15-security-advisories-github-secret-scanning-and-codeql-are-generally-available/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE/CVSS Scoring](https://nvd.nist.gov/vuln/detail/CVE-2026-40582) (example CVE)
