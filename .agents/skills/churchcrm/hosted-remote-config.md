---
title: "Hosted remote config branches"
intent: "External and Notifications are live hosts for every install and stay in the repo"
tags: ["plugins","notifications","github","maintenance"]
prereqs: ["[[repo-health]]", "[[plugin-registry]]"]
complexity: "beginner"
---

# Hosted remote config branches

| Branch | Role |
|--------|------|
| `External` | Live host for `approved-plugins.json` and `notifications.json`, fetched by `CentralServices` |
| `Notifications` | Broadcast-only host |

They are orphan hosting branches with no PR against master by design. Repo-health Check 4 excludes them.

Raw URLs used by running churches:

- https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json
- https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json

`ApprovedPluginRegistry` has no bundled fallback: a failed fetch gives an empty registry for the session, and `community-plugin-lifecycle.spec.js` (which waits for `#approvedPluginsList .btn-install-approved`) then times out.

## Lock

Ruleset [Protect External and Notifications](https://github.com/ChurchCRM/CRM/rules/23858586) blocks deletion and force-push. A 422 from `gh api -X DELETE …/git/refs/heads/External` is the lock working; stop there.

## How to edit

```bash
git fetch origin External
git worktree add /tmp/crm-external External
# edit approved-plugins.json or notifications.json
# open a PR with base External
```

`src/ChurchCRM/Remote/CentralServices.php` documents the same worktree flow.

## Related

- [`plugin-registry.md`](./plugin-registry.md)
- [`repo-health.md`](./repo-health.md)
