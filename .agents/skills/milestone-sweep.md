# Milestone PR Sweep

Process every open PR in a GitHub milestone end-to-end: rebase on master, address review comments, lint+build, push, resolve threads, and produce a summary report.

## Invocation

`/milestone-sweep <number>` or pass the milestone number as the argument when invoking this skill.

## Pre-flight (MANDATORY — abort if any fails)

1. `git status --short` is empty (no uncommitted changes)
2. `git branch --show-current` is `master`
3. `git fetch origin master && git pull --ff-only origin master` succeeds
4. `gh auth status` shows authenticated
5. `gh api /repos/{owner}/{repo}/milestones/<num>` returns 200 (milestone exists)

If any pre-flight fails: stop and report. Do NOT attempt fixes silently.

## Per-PR workflow (sequential, not parallel — shared working tree)

For each PR returned by `gh pr list --milestone <num> --state open --json number,title,headRefName,author,isDraft`:

1. **Skip drafts** — record as `skipped: draft` and continue.
2. `gh pr checkout <num>` — single source of truth for the right branch + remote.
3. `git fetch origin master && git rebase origin/master`.
   - Conflict → `git rebase --abort`, record as `blocked: rebase conflict`, continue to next PR.
4. **Read review state**:
   - `gh pr view <num> --json reviews,reviewDecision,comments`
   - `gh api repos/{owner}/{repo}/pulls/<num>/comments` for inline thread comments
5. **Apply Copilot/reviewer suggestions** — one logical edit per suggestion, separate commits.
6. **Verify per CLAUDE.md Mandatory Pre-Commit Checklist**:
   - `npm run lint`
   - Appropriate build: `npm run build:webpack` (JS only), `npm run build:php` (PHP only), or `npm run build` (mixed)
   - Lint/build failure that the agent cannot fix → record as `blocked: <stage> failed`, continue.
7. **Show diff and request user approval** before committing (CLAUDE.md "Mandatory Code Review Before Any Commit" — no exceptions, even in a sweep).
8. After approval: `git add` + `git commit` per logical change with conventional messages.
9. `git push --force-with-lease` (rebase rewrites history; never `--force`).
10. **Resolve addressed review threads** via `mcp__github__resolve_review_thread`. If thread node IDs aren't surfaced, fall back to one PR comment listing each addressed thread URL + the commit SHA that fixed it (CLAUDE.md "Always Resolve PR Comments After Push").
11. Record outcome in the running results array.

## Stop-and-escalate conditions

Halt the entire sweep and ask the user when any PR exhibits:

- Architectural decision needed (interface change, new module boundary, schema rewrite)
- Security-sensitive code path (auth, permissions, crypto, untrusted input)
- Reviewer comment requests behavior change, not just a code-style fix
- Tests fail after a fix attempt and root cause isn't in the PR diff
- More than one rebase conflict in the same PR

## Final step

1. `git checkout master` to leave a clean working state.
2. Write a summary report to `/tmp/milestone-<num>-summary.md`:

```markdown
# Milestone <num> Sweep — YYYY-MM-DD

| PR# | Title | Status | Actions Taken | Blockers |
|-----|-------|--------|---------------|----------|
| #1234 | Fix avatar lightbox | ✅ pushed | rebased, applied 3 Copilot fixes, lint+build pass, threads resolved | — |
| #1240 | Locale dropdown | ⚠️ blocked | rebased | rebase conflict in src/locale/i18n/en_US.json |
| #1245 | Add CSV export | ⏭️ skipped | — | draft |
```

Status values: `✅ pushed`, `⚠️ blocked: <reason>`, `⏭️ skipped: draft`, `🛑 escalated`.

3. Print the file path so the user can open it.

## Cross-references

- `.agents/skills/pr-review-fix.md` — single-PR review checklist (this skill applies it per-PR)
- `.agents/skills/churchcrm/pr-review.md` — full ChurchCRM PR review conventions
- `.agents/skills/churchcrm/git-workflow.md` — branch/commit/push rules
- CLAUDE.md → "Mandatory Code Review Before Any Commit"
- CLAUDE.md → "Mandatory Pre-Commit Checklist"
- CLAUDE.md → "Always Resolve PR Comments After Push"
