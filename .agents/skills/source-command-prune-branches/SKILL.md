---
name: "source-command-prune-branches"
description: "Delete local and your own merged remote branches"
---

# source-command-prune-branches

Use this skill when the user asks to run the migrated source command `prune-branches`.

## Command Template

# /prune-branches

Delete local branches whose work has landed, and optionally your own merged remote branches.

1. `node scripts/prune-branches.js` (add `--remote` to include `origin`). It is a dry run: show the output to the user.
2. Wait for a yes. Then re-run the same command with `--apply`.

The script never touches `master`, the current branch, a branch checked out in a worktree, or a branch with an open PR. A local branch is deleted only when its upstream is gone, it is merged into `origin/master`, or its PR is merged. Remote branches are deleted only for your own merged PRs, never `security/*`.
