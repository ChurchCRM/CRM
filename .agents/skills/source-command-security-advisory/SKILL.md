---
name: "source-command-security-advisory"
description: "Review GitHub security advisories in triage/draft status"
---

# source-command-security-advisory

Use this skill when the user asks to run the migrated source command `security-advisory`.

## Command Template
# Security Advisory Review

Work the GitHub security advisory queue as defined in [`.agents/skills/churchcrm/security-advisory-review.md`](../../.agents/skills/churchcrm/security-advisory-review.md).

Read that skill file now and follow it in order. Triage each advisory with `.agents/skills/churchcrm/security-report-triage.md` before deciding priority. Review the fix branch with `/pr-review`.

Pass a GHSA ID to start with one advisory, or none to list the queue.
