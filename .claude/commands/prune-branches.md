# /prune-branches

Delete local branches whose work has landed, and optionally your own merged remote branches.

1. `node scripts/maintainer/prune-branches.js` (add `--remote` to include `origin`). It is a dry run: show the output to the user.
2. Wait for a yes. Then re-run the same command with `--apply <plan id>` from the preview. It deletes nothing if the branches changed since the preview.

The script never touches `master`, the current branch, a branch checked out in a worktree, a branch with an open PR (any author), or a branch with unmerged work. A branch is deleted only when its tip is merged into `origin/master` or equals the head of one of your merged PRs. Remote branches are deleted only for your own merged PRs, never `security/*`.
