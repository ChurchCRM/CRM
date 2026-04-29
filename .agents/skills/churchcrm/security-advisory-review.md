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

```json
{
  "ghsa_id": "GHSA-cwp8-rm8g-q5c9",
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
| `vulnerabilities[].patched_versions` | Fixed versions | Empty = needs fix, version = patched |
| `cwe_ids` | Weakness categories | Helps classify the type of bug |
| `credits` | Attribution | Ensure reporter is credited properly |

## Workflow for Reviewing Draft Advisories

### 1. Fetch the Advisory

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header 'X-GitHub-Api-Version: 2026-03-10' | jq '.'
```

### 2. Extract Key Details

```bash
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA_ID} \
  --header 'X-GitHub-Api-Version: 2026-03-10' | jq '{
    ghsa_id,
    summary,
    severity,
    state,
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
- **Proof of concept**: Can you reproduce it locally?
- **Attack vector**: Network? Authenticated? Requires admin?
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

### Endpoint Requires Specific API Version

The security advisories endpoint requires `X-GitHub-Api-Version: 2026-03-10` or later:

```bash
# ✅ CORRECT
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA} \
  --header 'X-GitHub-Api-Version: 2026-03-10'

# ❌ WRONG — older API version may return different schema
gh api repos/ChurchCRM/CRM/security-advisories/{GHSA}
```

## Related Skills

- [`security-best-practices.md`](security-best-practices.md) — Vulnerability handling, disclosure timeline
- [`github-interaction.md`](github-interaction.md) — Working with GitHub API and publishing advisories
- [`authorization-security.md`](authorization-security.md) — Authentication and authorization patterns

## See Also

- [GitHub REST API: Repository Security Advisories](https://docs.github.com/en/rest/security-advisories/repository-advisories)
- [Managing Security Advisories in GitHub](https://docs.github.com/code-security/security-advisories/managing-security-advisories)
