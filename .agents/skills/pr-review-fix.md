# PR Review & Fix Workflow
1. Read PR review comments via `gh pr view <num> --comments`
2. Verify current branch matches PR branch (`git branch --show-current`)
3. Apply each Copilot suggestion as a separate logical edit
4. Run lint + build to verify
5. Commit with conventional message, push
6. Resolve all review threads via `gh api`
7. Append any new patterns learned to .agents/skills/churchcrm/git-workflow.md
