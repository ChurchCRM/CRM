---
title: Git Workflow
intent: How agents commit and push on ChurchCRM.
---

# Git Workflow

## Must

- Branch from current `master`. `fix/issue-N-short` or `feature/short`
- Every PR links an open issue
- Imperative subject, under 72 chars
- `npm run lint` and the matching build before you ask to commit
- Show the diff. Wait for yes before commit
- Title and body match the current diff
- Never `--force`. `--force-with-lease` only after an approved rebase
- Do not merge `master` into a contributor branch or push to their branch unless asked
- Do not run `locale:build` or commit `messages.po` on a feature branch

## Commit freely, push only on approval

`git commit` and `git push` are separate gates.

- Commit after lint + build + shown diff + yes on the diff
- Push only when the **latest** user message says push ("push", "push it", "go ahead and push"). "lgtm" on a diff is commit-only

Every push runs the full GitHub CI matrix. A `PreToolUse` hook in `.claude/settings.json` prompts on `git push`. If that prompt appears and the user did not just ask to push, stop.

## Mandatory Pre-Push Biome Check

`.githooks/pre-push` runs `npm run lint`. `package.json` `prepare` sets `core.hooksPath=.githooks`. No-op when `$CI` or `$GITHUB_ACTIONS` is set.

Agents run `npm run lint` themselves before asking to push. Never `git push --no-verify` unless the maintainer authorizes a hotfix and the PR names the bypass.

### Pre-Push Checkpoint Owns Local Deterministic Validation

Before adding a check to a push/PR GitHub Actions workflow, ask whether the same
failure can be detected deterministically in the developer checkout without a
GitHub-only service, clean-room environment, or multi-runtime matrix.

- If **yes**, put it in the local commit/pre-push checkpoint first. Do not make
  push CI the first place contributors learn about it.
- If **no**, it belongs in CI (examples: clean install, Docker integration,
  multiple PHP/database versions, packaging, artifact/release behavior).
- Expensive exhaustive/regression sweeps that are not appropriate on every
  developer push belong in the nightly workflow.
- CI may retain a cheap defense-in-depth copy of a local check when bypassed
  hooks or external contributors make that necessary, but the local checkpoint
  remains the primary/earliest enforcement point.

When changing CI, the PR description must state why each newly added CI-only
check cannot run meaningfully before push.

## After push

Do not approve or merge. Do not close issues unless the maintainer answers yes to a direct question.
