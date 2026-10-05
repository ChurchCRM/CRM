---
name: "source-command-security-report-triage"
description: "Migrated source command `security-report-triage`"
---

# source-command-security-report-triage

Use this skill when the user asks to run the migrated source command `security-report-triage`.

## Command Template

# Security Report Triage

Run the vulnerability-report read defined in [`.agents/skills/churchcrm/security-report-triage.md`](../../.agents/skills/churchcrm/security-report-triage.md): does the claimed code path exist, what are the real preconditions, is there a reproducible proof of concept, and does the stated severity match the evidence.

Read that skill file now and follow it exactly, in order. The output is a maintainer-only triage read (grounded / plausible-unverified / inflated / not-a-vulnerability) — never a reply to the reporter, and nothing here gets posted publicly.

Paste or point at the report to triage when invoking this.
