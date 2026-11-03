# /push-pr

Push the current branch and open its pull request, following [`.agents/skills/churchcrm/git-workflow.md`](../../.agents/skills/churchcrm/git-workflow.md). Typing this command is the approval to push.

1. Stop on `master`. Stop and list the changes if the tree is dirty.
2. Run `npm run lint`. Never `--no-verify`. On failure, report it and stop.
3. `git push -u origin <branch>`. If the branch already has an open PR, push only.
4. `gh pr create --base master`. Follow [`.agents/skills/pr-description-guidelines.md`](../../.agents/skills/pr-description-guidelines.md): imperative title under 72 chars, body that matches the current diff, `Fixes #N` for user-visible changes (otherwise say why there is no issue), the UI pass statement for UI changes, and the attribution line from the session.
5. Labels are exact names from `gh label list --repo ChurchCRM/CRM`. No milestone.
6. Report the PR URL.

Arguments are extra instructions for the PR body.
