---
title: "Hosted remote config branches"
intent: "External and Notifications are live hosts for every install. Never prune them."
tags: ["plugins","notifications","github","maintenance"]
prereqs: ["[[repo-health]]", "[[plugin-registry]]"]
complexity: "beginner"
---

# Hosted remote config branches

## What they are

| Branch | Role |
|--------|------|
| `External` | Live host for `approved-plugins.json` and `notifications.json`. This is what `CentralServices` on master / 7.7.0 fetches. |
| `Notifications` | Older broadcast-only host. Keep it. Do not treat it as a leftover. |

They are **orphan hosting branches**, not feature branches. They have no PR against master by design. Repo-health Check 4 must not classify them as "PR-less → delete".

Raw URLs used by running churches:

- https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json
- https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json

## Lock

Ruleset [Protect External and Notifications](https://github.com/ChurchCRM/CRM/rules/23858586) (id `23858586`) is **active**:

- blocks **deletion**
- blocks **force-push**

If `gh api -X DELETE …/git/refs/heads/External` returns **422**, stop. That is the lock working.

## Lesson (21–22 Sep 2026)

1. Check 4 deleted `External` because it had no PR (#9961).
2. `ApprovedPluginRegistry` has no bundled fallback on shipped 7.7.0. Fetch 404 → session registry `[]`.
3. `community-plugin-lifecycle.spec.js` waits for `#approvedPluginsList .btn-install-approved` then clicks `hello-world`. Empty list → UI shard 2 timeout (#9969).
4. Branch was recreated from reconstructed content, then restored to last known allowlist from surviving PR commit `d5902d02` / #8928.

Original pre-delete SHA was not recovered from GitHub. Last complete file is still readable at that commit even if the branch moves.

## How to edit

```bash
git fetch origin External
git worktree add /tmp/crm-external External
# edit approved-plugins.json or notifications.json
# prefer a PR with base External over a direct push
```

`src/ChurchCRM/Remote/CentralServices.php` documents the same worktree flow.

## Related

- [`plugin-registry.md`](./plugin-registry.md)
- [`repo-health.md`](./repo-health.md)
