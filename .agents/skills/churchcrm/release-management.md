---
title: Release Management
intent: Orchestrate an approved ChurchCRM release using the canonical release runbook.
---

# Release Management

Use this skill to prepare, check, start, publish, or verify a ChurchCRM release.
Follow [the release process](../../../docs/maintainers/release-process.md) for the steps and
current automation. Use [the locale pipeline](../../../docs/maintainers/locale-pipeline.md)
for localization details.

## Agent gates

- Inspect current GitHub and repository state; memory is not evidence of readiness.
- Identify the release candidate by exact commit SHA. A change to that SHA invalidates its prior green checks.
- Do not start a draft or publish without the maintainer's explicit approval for that action. Approval to draft is not approval to publish.
- Do not replace or delete an existing release or tag without explicit approval.
- Security gate: only a High advisory blocks, and only while the code on the release commit is still exploitable. Read each draft High and check `master`; a draft state or a missing `Fixes` link does not decide it. Medium and Low never block. List every blocking GHSA ID. Only the maintainer lifts a block, by naming the ID.
- Report **READY FOR DRAFT** or **BLOCKED** with the evidence and remaining blockers. After publication, report **RELEASE VERIFIED** only after completing the runbook's post-release checks.

Use the [Release Notes skill](./release-notes.md) to prepare notes, and the
[Release Announcement skill](./release-announcement.md) for post-release community
actions. This skill does not replace their step-specific instructions.
