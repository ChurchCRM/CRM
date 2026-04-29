<<<<<<< HEAD
---
title: "Security Advisory Review"
intent: "Access and review private/draft security advisories using GitHub API"
tags: ["security","github","advisories","vulnerability"]
prereqs: ["security-best-practices.md"]
complexity: "intermediate"
---

# Skill: Security Advisory Review

## Context
This skill covers accessing and reviewing private/draft security advisories for the ChurchCRM/CRM repository. Draft advisories are not publicly visible but can be accessed via the GitHub REST API if you have the required permissions.

## Accessing Private/Draft Advisories

### Prerequisites

- GitHub CLI installed and authenticated (`gh auth status`)
- Token scopes: `repo` or `repository_advisories:read`
  - Personal access tokens (classic) must have at least `repo` scope
  - GitHub Apps with `repository_advisories:read` permission can read draft advisories
- You must be a security manager, repository administrator, or collaborator on the security advisory

### Fetch a Draft Advisory by GHSA ID

Use the repository security advisories endpoint with the advisory's GHSA ID:

```bash
# Format: gh api repos/{owner}/{repo}/security-advisories/{ghsa_id}
gh api repos/ChurchCRM/CRM/security-advisories/GHSA-xxxx-xxxx-xxxx \
  --header 'X-GitHub-Api-Version: 2026-03-10'
```

**Key headers:**
- `X-GitHub-Api-Version: 2026-03-10` — required for latest advisory schema
- Authorization is automatic via `gh` authenticated session

### Parse the Advisory Response

The API returns a JSON object with these critical fields:
=======
# Security Advisory Review Process

This skill documents how to access, analyze, and respond to GitHub security advisories for ChurchCRM.

## Accessing Unpublished Advisories

### Via GitHub Web UI

Draft advisories **are** visible to repo maintainers in the GitHub web UI under **Security → Advisories**. Non-maintainers cannot see them.

### Via GitHub CLI (useful for automation/scripting)

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28"
```

### Required Headers

- `X-GitHub-Api-Version: 2022-11-28` — mandatory for security advisory endpoints
- Standard GitHub authentication via `gh` CLI or `GITHUB_TOKEN`

### Example Response Structure
>>>>>>> master

```json
{
  "ghsa_id": "GHSA-cwp8-rm8g-q5c9",
<<<<<<< HEAD
  "state": "triage",  // "triage" = draft, "published" = public
  "summary": "Incomplete fix for CVE-2026-40582",
  "description": "Full markdown description of the vulnerability",
  "severity": "critical",  // critical, high, medium, low
  "cve_id": null,  // Assigned CVE ID or null if not yet assigned
  "cvss_severities": {
    "cvss_v3": {
      "score": 9.6,
      "vector_string": "CVSS:3.1/AV:N/AC:L/..."
    }
  },
  "cwe_ids": ["CWE-287", "CWE-304"],  // Weakness classifications
  "author": { "login": "f3nrir77", ... },  // Reporter
  "created_at": "2026-04-24T19:29:58Z",
  "published_at": null,  // null while in draft
  "vulnerabilities": [
    {
      "package": {
        "ecosystem": "composer",
        "name": "ChurchCRM/CRM"
      },
      "vulnerable_version_range": ">= 7.2.0, <= 7.2.2",
      "patched_versions": ""  // Empty string = no fix yet
    }
  ],
  "credits": [...],
  "credits_detailed": [...],
  "collaborating_users": [...],
  "collaborating_teams": []
}
```

### Key Fields to Review

| Field | Purpose | Review Focus |
|-------|---------|--------------|
| `state` | Advisory status | `triage` = draft, `published` = live |
| `severity` | CVSS impact rating | Determines urgency and messaging |
| `description` | Full vulnerability details | Root cause, PoC, reproduction steps |
| `vulnerabilities[].vulnerable_version_range` | Affected versions | Which releases are exploitable |
| `cwe_ids` | Weakness categories | Helps classify the type of bug |
| `credits` | Attribution | Ensure reporter is credited properly |

## Workflow for Reviewing Draft Advisories


```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header 'X-GitHub-Api-Version: 2026-03-10' | jq '.'
```

### 2. Extract Key Details

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
    summary,
    severity,
    cwe_ids,
    created_at,
    author: .author.login,
    affected_versions: .vulnerabilities[0].vulnerable_version_range,
    patched_versions: .vulnerabilities[0].patched_versions
  }'
```

### 3. Review the Description

Parse the `description` field for:
- **Root cause**: What code path or logic allows the vulnerability?
- **Claimed fix**: What code changes are recommended?

### 4. Verify the Fix Status

Check if `patched_versions` is empty or has a version:
- **Empty string** → No fix released yet; advisory is a report of an unfixed bug
- **Contains version** (e.g., `"7.3.0"`) → Fix is claimed to be in that release
- If fix is claimed, verify it actually exists in that tag by checking the codebase

### 5. Check Collaborators

Review `collaborating_users` and `collaborating_teams`:
- These are people/teams with access to edit or publish the advisory
- Important for coordination on disclosure timeline

## Common Patterns

### Draft Advisory Not Yet Published

```bash
# Advisory in "triage" state with null published_at
{
  "state": "triage",
  "published_at": null,
  "severity": "critical",
  "summary": "...",
  "cve_id": null  # CVE assignment pending
}
```

Indicates: Report received but not yet coordinated for publication. Review for accuracy, impact severity, and affected version ranges.

### Incomplete Fix in Previous Release

```bash
{
  "description": "The fix for CVE-2026-40582 is incomplete. The hardening commit was reverted...",
  "vulnerabilities": [
    {
      "vulnerable_version_range": ">= 7.2.0, <= 7.2.2",
      "patched_versions": ""  # Empty = no fix yet
    }
  ]
}
```

Indicates: Earlier advisory claimed a fix, but this new advisory shows the fix was incomplete. Requires new remediation and may warrant a separate CVE.

### Published Advisory with Fix

```bash
{
  "state": "published",
  "published_at": "2026-04-28T...",
  "vulnerabilities": [
    {
      "vulnerable_version_range": "< 7.3.0",
      "patched_versions": "7.3.0"
    }
  ]
}
```

Indicates: Live public advisory. Fix is available in named version. Users should upgrade.

## API Limitations & Troubleshooting

### Advisory Not Found (404)

```bash
# ❌ Returns 404
gh api repos/ChurchCRM/CRM/security-advisories/GHSA-xxxx-xxxx-xxxx
```

Causes:
1. **GHSA ID is incorrect** — double-check spelling
2. **Advisory doesn't exist** — reporter may not have created it yet
3. **Wrong repo** — advisory is for a different repository
4. **Insufficient permissions** — you're not a security manager or collaborator on this advisory

Solution: Ask the reporter to share the exact advisory ID or direct link.

```bash
# ✅ CORRECT
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA} \
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA}
```

## Related Skills
- [`github-interaction.md`](github-interaction.md) — Working with GitHub API and publishing advisories
- [`authorization-security.md`](authorization-security.md) — Authentication and authorization patterns

## See Also

- [GitHub REST API: Repository Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
- [Managing Security Advisories in GitHub](https://docs.github.com/code-security/security-advisories/managing-security-advisories)
=======
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
git checkout -b security/fix-api-2fa-bypass-7.3.1
```

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
>>>>>>> master
