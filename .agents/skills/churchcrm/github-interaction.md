# Skill: GitHub Interaction — concise checklist

Purpose: practical, step-by-step actions for handling reviews, commits, and PRs. This file is an actionable companion to the canonical `git-workflow.md` (policy) and the `gh-cli` skill (commands).

Quick checklist — Addressing reviews
- Read comments and ask clarifying questions if needed.
- Make one focused change per review thread; add tests where applicable.
- Mark comments resolved and add a short reply summarizing the change.
- Run relevant tests and clear logs before testing:

```bash
rm -f src/logs/$(date +%Y-%m-%d)-*.log
```

Quick checklist — Commit
- Stage only intended files: `git add <files>`
- Use the commit format from `git-workflow.md` (imperative, reference issue).
- Verify staged diff: `git diff --staged`

Quick checklist — Create PR (example using `gh`)
- Push branch: `git push origin <branch>`
- Create PR with a template body:

```bash
gh pr create --title "Fix issue #1234: Short title" --body-file .github/PULL_REQUEST_TEMPLATE.md --base master
```

- Add reviewers and link issues (`Fixes #1234`) in the PR description.
- Wait for CI; respond to review comments and iterate.

Best practices (short)
- Keep commits small and focused.
- One issue per PR.
- Always run tests locally before pushing.
- Do not merge without human review and passing CI.

See also:
- `git-workflow.md` for branching, commit message format, and the full pre-commit checklist.
- `gh-cli` skill (install via `npx skills add https://skills.sh/ --skill gh-cli`) for concrete `gh` commands.

If you prefer, I can convert this into a checklist file that the CI or editors can surface (e.g., a JSON checklist or PR template snippet).