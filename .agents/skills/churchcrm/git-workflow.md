---
title: Git Workflow
intent: How agents commit and push on ChurchCRM.
---

# Git Workflow

## Must

- Branch from current `master`. `fix/issue-N-short` (or `fix/short`, `ci/short` with no issue) or `feature/short`
- Link an open issue when the PR changes user-visible behavior (feature or bug fix). CI, docs, tooling, dependency, and trivial PRs need none; say why in the PR body
- Imperative subject, under 72 chars
- Branch every PR from `master`, one per independent change. Base a PR on another unmerged PR only when it needs that PR's code; a PR that removes what the others stopped using waits until they merge, then branches from `master`. The last PR of an epic says `Part of #epic`; a maintainer closes the epic
- Do not set a milestone by hand. On merge, `.github/workflows/pr-milestone-stamp.yml` stamps the PR and every issue it fixes with the `package.json` `version` (7.8.0 → milestone `7.8.0`) and closes those issues if they are still open. When the PR finishes an issue, the body says `Fixes #N` (not `refs`)
- A bug uses the GitHub issue type Bug. Do not add a `bug` label. Other issue labels are one of `enhancement`, `question`, `Documentation`, `Epic`, `refactor`, or `cleanup`, plus the area labels that fit (`Feature: ...`, `UI`, `API`, `Security`, `Platform: ...`). `Integration Testing` is Cypress and other automated tests of the app. Playwright work (captures, screenshots, videos, the marketing pipeline) is `Marketing`, never `Integration Testing`. Copy exact names from `gh label list --repo ChurchCRM/CRM`. Never invent a label. Do not use `feature` (use `enhancement`), `volunteer` (use `Feature: Volunteer`), or `translation` (use `Localization`). `Epic` is only for a parent that tracks child issues. Do not add `Stale` by hand. The Monday hygiene workflow asks for a reply after 45 quiet days and closes 14 days later if nobody answers. `Security`, `security-delete-required`, `Epic`, and `good first issue` stay open. `dependencies` is Dependabot only; human dependency work uses `Package Dependencies`
- `npm run lint` and the matching build before you ask to commit
- UI change: check the pages in a real browser before you ask to commit, in light and dark themes, at desktop, tablet (about 820px), and phone width (browsers stop near 500px). Look for column widths, wrapping, horizontal scroll, and working menus. Fix what is off, then look again
- UI PR: attach screenshots of the important pages (desktop, plus tablet or phone) to the PR description. Take them in that browser pass on seeded data, never real names or emails. State in the PR body that the light/dark and tablet/mobile pass was done
- Before you ask to push, run every Cypress spec the change touches (new and existing) against the seeded test stack: `npm run docker:test:reset:db`, then `npx cypress run --config-file cypress/configs/docker.config.ts --spec "a.spec.js,b.spec.js"`. Report the pass counts. Push CI is not where a spec first fails
- Stage files by explicit path, never `git add -A` / `.`. Before commit, `git status --short` and `git diff --cached --name-status` must list only files the task expects; unstage or delete strays (editor/`sed -i` backups like `*-E`, generated files, symlinks) first
- Show the diff. Wait for yes before commit
- Title and body match the current diff
- Never `--force`. `--force-with-lease` only after an approved rebase
- Fixing an existing PR: push to that PR's head branch. A push to any other branch can auto-open a duplicate PR
- Keep unrelated fixes (a CI retry beside a security fix) in their own PR
- Do not merge `master` into a contributor branch or push to their branch unless asked
- Do not run `locale:build` or commit `messages.po` on a feature branch

## Commit freely, push only on approval

`git commit` and `git push` are separate gates.

- Commit after lint + build + shown diff + yes on the diff
- Push only when the **latest** user message says push ("push", "push it", "go ahead and push"). "lgtm" on a diff is commit-only
- Exception: no new approval to push a fix for failing CI or for a merge conflict on a branch you already pushed with approval. A conflict fixed by rebase pushes with `--force-with-lease`. Run `npm run lint` first, and push only the fix. New work still waits for "push"

Every push runs the full GitHub CI matrix (about 15-20 billable minutes).

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

### One lint entry point

`npm run lint` runs `scripts/lint.js`, which runs every check in parallel. The pre-push hook and the `code-quality.yml` job call it and nothing else.

- Add a check as one entry in the `checks` map in `scripts/lint.js`. Do not add `lint:*` or `validate:*` npm scripts, and do not add a separate CI step for it. `lint:fix` is the only other lint script.
- A check must take a few seconds, need no Docker, database, or network, and print its findings to stdout or stderr with a non-zero exit.
- The pre-commit hook runs only checks scoped to staged files (`locale-check --staged`, the changed-file PHP validators). Whole-repo checks go in `scripts/lint.js`.
- A check that always exits 0 is not a check. Test a new one against a bad input before you add it.

## After push

If the PR work used a `git worktree` and the session has been idle 10 minutes after the push (no review threads or CI you were asked to watch), ask once whether to remove it (`git worktree remove <path>`, then delete the local branch if merged). Never remove it unasked.

Do not approve or merge. Do not close an issue by hand unless the maintainer answers yes. A finished issue is closed by `Fixes #N` on the merged PR.

A push that fixes review comments is not done until the threads it fixed are resolved. Check each thread against the pushed code first, then resolve it (`resolveReviewThread` in the GraphQL API). Reply with the commit SHA when the fix is not obvious. Leave a thread open, with a reply, when it is not fixed or you disagree. List each unresolved thread when you report back.
