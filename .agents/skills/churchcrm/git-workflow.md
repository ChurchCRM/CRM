---
title: Git Workflow
intent: How agents commit and push on ChurchCRM.
---

# Git Workflow

## Must

- Branch from current `master`. `fix/issue-N-short` (or `fix/short`, `ci/short` with no issue) or `feature/short`
- Link an open issue when the PR changes user-visible behavior (feature or bug fix). CI, docs, tooling, dependency, and trivial PRs need none; say why in the PR body
- Imperative subject, under 72 chars
- Milestone for a PR is the `version` in `package.json` (7.8.0 → milestone `7.8.0`). Do not guess from the open milestone list or the latest release. When the PR finishes an issue, the body says `Fixes #N` (not `refs`). On merge, `.github/workflows/pr-milestone-stamp.yml` copies that milestone onto those issues and closes them if they are still open
- A bug uses the GitHub issue type Bug. Do not add a `bug` label. Other issue labels are one of `enhancement`, `question`, `Documentation`, `Epic`, `refactor`, or `cleanup`, plus the area labels that fit (`Feature: ...`, `UI`, `API`, `Security`, `Platform: ...`). Copy exact names from `gh label list --repo ChurchCRM/CRM`. Never invent a label. Do not use `feature` (use `enhancement`), `volunteer` (use `Feature: Volunteer`), or `translation` (use `Localization`). `Epic` is only for a parent that tracks child issues. Do not add `Stale` by hand. The Monday hygiene workflow asks for a reply after 45 quiet days and closes 14 days later if nobody answers. `Security`, `security-delete-required`, `Epic`, and `good first issue` stay open. `dependencies` is Dependabot only; human dependency work uses `Package Dependencies`
- `npm run lint` and the matching build before you ask to commit
- UI change: check the pages in a real browser before you ask to commit, at desktop, tablet (about 820px), and phone width (browsers stop near 500px). Look for column widths, wrapping, horizontal scroll, and working menus. Fix what is off, then look again
- UI PR: attach screenshots of the important pages (desktop, plus tablet or phone) to the PR description. Take them in that browser pass on seeded data, never real names or emails. State in the PR body that the tablet/mobile pass was done
- Before you ask to push, run every Cypress spec the change touches (new and existing) against the seeded test stack: `npm run docker:test:reset:db`, then `npx cypress run --config-file cypress/configs/docker.config.ts --spec "a.spec.js,b.spec.js"`. Report the pass counts. Push CI is not where a spec first fails
- Stage files by explicit path, never `git add -A` / `.`. Before commit, `git status --short` and `git diff --cached --name-status` must list only files the task expects; unstage or delete strays (editor/`sed -i` backups like `*-E`, generated files, symlinks) first
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

Do not approve or merge. Do not close an issue by hand unless the maintainer answers yes. A finished issue is closed by `Fixes #N` on the merged PR.

A push that fixes review comments is not done until the threads it fixed are resolved. Check each thread against the pushed code first, then resolve it (`resolveReviewThread` in the GraphQL API). Reply with the commit SHA when the fix is not obvious. Leave a thread open, with a reply, when it is not fixed or you disagree. List each unresolved thread when you report back.
