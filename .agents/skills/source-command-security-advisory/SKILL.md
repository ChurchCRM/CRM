---
name: "source-command-security-advisory"
description: "Review GitHub security advisories in triage/draft status"
---

# source-command-security-advisory

Use this skill when the user asks to run the migrated source command `security-advisory`.

## Command Template

# Security Advisory Review

Load the security advisory review skill to check and manage triage/draft advisories.

The skill provides a 4-step workflow:
1. **Fetch** current advisories via GitHub API
2. **Analyze** severity and state
3. **Investigate** code impact
4. **Categorize** by action (work now / schedule / hold)

GitHub's recommended approach keeps fixes private until release:
- Work in a `security/fix-{GHSA_ID}` branch
- Commit directly (no public PR)
- Merge to master once tests pass
- Publish the advisory when the patched version is released

To begin reviewing advisories, invoke the skill:

```
/security-advisory-review
```

Or check the skill documentation for specific advisory workflows.
