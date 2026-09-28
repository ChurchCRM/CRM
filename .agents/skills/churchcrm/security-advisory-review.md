---
title: "Security Advisory Review Process"
intent: "Regular review workflow for GitHub security advisories in triage/draft status"
tags: ["security", "audit", "github", "recurring"]
prereqs: ["[[security-best-practices]]", "[[github-interaction]]"]
complexity: "advanced"
---
# Security Advisory Review Process

Regular (every few days) review of advisories in triage and draft status. Identify scope, prioritize, and decide whether to start fixes.

**Do not commit example-specific content to this skill.** Keep it general so it applies to any advisory.

## Accessing Unpublished Advisories

### Via GitHub CLI (Required)

Unpublished (draft and triage) advisories are NOT accessible via the web UI. Use `gh` CLI with the security advisory endpoint:

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28"
```

### Required Headers

- `X-GitHub-Api-Version: 2022-11-28` — mandatory for security advisory endpoints
- Standard GitHub authentication via `gh` CLI (uses your existing GitHub token)

### Response Structure

Each advisory returns:

```json
{
  "ghsa_id": "GHSA-xxxx-xxxx-xxxx",
  "cve_id": "CVE-XXXX-XXXXX",
  "summary": "vulnerability description",
  "description": "detailed explanation",
  "severity": "critical|high|moderate|low",
  "cvss": {
    "score": 0.0,
    "vector_string": "CVSS:3.1/..."
  },
  "cwes": [{"cwe_id": "CWE-XXX"}],
  "state": "triage|draft|published|closed",
  "vulnerabilities": [
    {
      "package": {"ecosystem": "composer", "name": "ChurchCRM/CRM"},
      "vulnerable_version_range": "< 7.3.1",
      "patched_versions": "7.3.1",
      "vulnerable_functions": ["functionName"]
    }
  ],
  "created_at": "2026-XX-XXT00:00:00Z",
  "updated_at": "2026-XX-XXT00:00:00Z",
  "published_at": null,
  "closed_at": null
}
```

### States Explained

- `triage` — submitted and under review; fix status unknown
- `draft` — reviewer has determined fix is needed; fix may be in progress or merged
- `published` — live advisory; GitHub alerts affected repos
- `closed` — rejected, duplicate, or superseded

## Reviewing Advisories (Regular Process)

Run this every few days to check status and identify work:

### Step 1: Fetch Current Advisories

For each GHSA ID in triage or draft:

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28" | jq '{
    ghsa_id: .ghsa_id,
    state: .state,
    severity: .severity,
    summary: .summary,
    score: (.cvss_severities[0].score // .cvss.score),
    vulnerabilities: [.vulnerabilities[] | {
      vulnerable_range: .vulnerable_version_range,
      patched_versions: .patched_versions
    }]
  }'
```

**Note:** GitHub API uses `cvss_severities` (since April 2025); fallback to deprecated `cvss.score` for older APIs.

### Step 2: Analyze Each Advisory

For each advisory, determine:

| Field | Action |
|-------|--------|
| `severity` | Critical/High → prioritize; Moderate/Low → schedule |
| `score` | ≥7.0 is serious; <5.0 is lower priority |
| `state` | `triage` = unknown fix status; `draft` = fix likely in progress/merged |
| `vulnerable_range` | Which versions are affected? |
| `patched_versions` | Are patches released or pending? |

### Step 3: Investigate Code Impact

For each advisory, verify scope:

```bash
# Find vulnerable code patterns (if mentioned in description)
grep -r "pattern_from_description" src/

# Check git history for related commits
git log --oneline --all | grep -i "keyword_from_summary"

# Review commit details if found
git show {commit-sha}
```

### Step 4: Categorize by Action

**Work on immediately:**
- Critical/High severity (CVSS ≥7.0)
- Simple to fix (1–2 file changes)
- Affects current/recent releases

**Schedule next cycle:**
- Moderate severity (CVSS 4–6)
- Depends on other refactoring
- Affects older versions only

**Hold/defer:**
- Already patched and released
- Requires major refactor
- Duplicate of another advisory

## Creating a Fix (When Needed)

### Recommended Workflow: Private Branch (No Public PR)

**GitHub best practice:** Work in a private fork or private branch to keep the unpatched vulnerability hidden until release.

**IMPORTANT: Do NOT push the security-fix branch to the public `ChurchCRM/CRM` repository.** Even without a PR, pushed branches and their diffs are visible via `git ls-remote`, the GitHub API, and the branches UI. Instead, use one of these approaches:

**Option A: Private Fork (Recommended)**
- Fork ChurchCRM/CRM to a private repository
- Push the security-fix branch only to your private fork
- After the fix is released in a public version, open a PR against the main repo with the published commit

**Option B: Local-Only Branch**
- Create and commit locally (`git checkout -b security/fix-{GHSA_ID}`)
- Test locally
- Have a maintainer pull from your local machine (`git pull /path/to/your/local/repo security/fix-{GHSA_ID}`)
- Merge and push to master from their authenticated session

**Merge to master after maintainer review:**
```bash
# Maintainer receives the fix via Option A or B
git checkout master
git pull origin master
git merge --no-ff security/fix-{GHSA_ID}
git push origin master
```

**Why not a public branch?**
- Branch names embed the GHSA ID, exposing the vulnerability before release
- Full diffs are fetchable before patches are available
- The advisory is published only when the patched version is released

### Code Changes

1. **Fix the vulnerability** — Implement the patch (may involve restoring prior code or new logic)
2. **Update API/docs** — Add/update `@OA\*` annotations if endpoints changed
3. **Write tests** — Cover the security vectors mentioned in the advisory
4. **Run full test suite** — Ensure no regressions

### Test Coverage Requirements

Tests should verify all attack vectors from the advisory:

- ✅ Happy path (legitimate usage still works)
- ✅ Attack scenarios (demonstrate vulnerability is fixed)
- ✅ Error handling (correct status codes, no information leakage)
- ✅ Edge cases (boundary conditions, empty inputs, etc.)

For authentication/authorization fixes specifically:

- ✅ Generic error messages (no username/email enumeration)
- ✅ Account lockout enforcement (if applicable)
- ✅ 2FA/MFA enforcement (if applicable)
- ✅ Rate limiting (if applicable)

## Committing the Fix

### Commit Message Format

```
security: fix {GHSA_ID} — {short description}

- Root cause: {what was vulnerable}
- Fix: {changes made}
- Tests: {test coverage summary}
- Affects: {version range}

Resolves GHSA-{GHSA_ID}
Co-Authored-By: Claude Haiku 4.5 <noreply@anthropic.com>
```

## Validation Before Merge

### Pre-Merge Checklist

Before merging to master:

- [ ] Tests passing locally (`npm test`)
- [ ] Linting passing (`npm run lint`)
- [ ] Build passing (`npm run build:webpack`)
- [ ] API/docs updated (if applicable)
- [ ] No regressions in other tests
- [ ] Commit message follows security format (see above)
- [ ] **Maintainer review and approval obtained** ⚠️

### Obtaining Maintainer Review

Per `maintainer-review-gates.md`, security fixes **require explicit maintainer review and sign-off** before merging to master, even though no public PR is opened. This prevents unauthorized or incomplete fixes from reaching released versions.

Contact a maintainer with:
- Advisory summary and CVSS score
- Affected versions and fix approach
- Test coverage summary
- Private fork or branch location (Option A/B above)

### Merge to Master (After Approval)

Once maintainer approves:

```bash
# Merge to master
git checkout master
git pull origin master
git merge --no-ff security/fix-{GHSA_ID}
git push origin master
```

**Note:** No public pull request is created for security fixes. The branch stays private until the patched version is released.

## Publishing the Advisory

After merging the fix and releasing a patched version:

1. **Wait for version release** — Ensure the patched version is published
2. **Update CVSS/CVE details** — Add final severity, CVE ID, etc.
3. **Publish via GitHub UI** — Navigate to Security → Advisories → Draft → Publish
4. **Or use CLI**:
   ```bash
   gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
     -X PATCH -f state=published \
     --header "X-GitHub-Api-Version:2022-11-28"
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

## After Merge: Update Advisory Metadata

Once the fix is merged and included in a release, update the advisory to mark it patched:

### Fetch Current Advisory

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28" | jq '.vulnerabilities'
```

### Update Patched Version

First, fetch the current advisory to get all existing vulnerability entries:

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header "X-GitHub-Api-Version:2022-11-28" | jq '.vulnerabilities' > current_vulns.json
```

Then, update the entries and send the complete array:

```bash
cat > /tmp/advisory-patch.json <<'EOF'
{
  "vulnerabilities": [
    {
      "package": {
        "ecosystem": "composer",
        "name": "ChurchCRM/CRM"
      },
      "vulnerable_version_range": "{INSERT_AFFECTED_VERSIONS}",
      "patched_versions": "{INSERT_PATCHED_VERSION}",
      "vulnerable_functions": []
    }
  ]
}
EOF

gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  -X PATCH \
  --input /tmp/advisory-patch.json \
  --header "X-GitHub-Api-Version:2022-11-28"
```

**IMPORTANT:** 
- Use `--input` for arrays; `-f field=value` fails with HTTP 422
- Send the **complete** `vulnerabilities` array to avoid removing other entries
- Replace `{INSERT_AFFECTED_VERSIONS}` and `{INSERT_PATCHED_VERSION}` with values verified for this advisory

### Version Format Rules

- `vulnerable_version_range`: Use ranges like `"< 7.3.1"` or `">= 7.2.0, <= 7.2.2"`
- `patched_versions`: Exact version string (e.g., `"7.3.1"`), or empty `""` if unfixed
- Case-sensitive; no wildcards

## References

- [GitHub Security Advisories](https://docs.github.com/en/code-security/security-advisories/repository-security-advisories/about-repository-security-advisories)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CVSS Scoring](https://www.first.org/cvss/)
- [GitHub REST API: Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
