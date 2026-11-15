---
title: Git Workflow
intent: How agents commit and push on ChurchCRM.
---

# Git Workflow

## Must

- Branch from current `master`. `fix/issue-N-short` (or `fix/short`, `ci/short` with no issue) or `feature/short`
- Link an open issue when the PR changes user-visible behavior (feature or bug fix). CI, docs, tooling, dependency, and trivial PRs need none; say why in the PR body
- Imperative subject, under 72 chars, for commits and PR titles. A squash merge makes the PR title the `master` commit subject
- Branch every PR from `master`, one per independent change. Base a PR on another unmerged PR only when it needs that PR's code; a PR that removes what the others stopped using waits until they merge, then branches from `master`. The last PR of an epic says `Part of #epic`; a maintainer closes the epic
- Do not set a milestone by hand. On merge, `.github/workflows/pr-milestone-stamp.yml` stamps the PR and every issue it fixes with the `package.json` `version` (7.8.0 → milestone `7.8.0`) and closes those issues if they are still open. When the PR finishes an issue, the body says `Fixes #N` (not `refs`)
- A bug uses the GitHub issue type Bug. Do not add a `bug` label. Other issue labels are one of `enhancement`, `question`, `Documentation`, `Epic`, `refactor`, or `cleanup`, plus the area labels that fit (`Feature: ...`, `UI`, `API`, `Security`, `Platform: ...`). `Integration Testing` is Cypress and other automated tests of the app. Playwright work (captures, screenshots, videos, the marketing pipeline) is `Marketing`, never `Integration Testing`. Copy exact names from `gh label list --repo ChurchCRM/CRM`. Never invent a label. Do not use `feature` (use `enhancement`), `volunteer` (use `Feature: Volunteer`), or `translation` (use `Localization`). `Epic` is only for a parent that tracks child issues. Do not add `Stale` by hand. The Monday hygiene workflow asks for a reply after 45 quiet days and closes 14 days later if nobody answers. `Security`, `security-delete-required`, `Epic`, and `good first issue` stay open. `dependencies` is Dependabot only; human dependency work uses `Package Dependencies`
- `npm run lint` and the matching build before you push, when the session's environment can run them. A check the environment cannot run never blocks a commit (see Sessions without a toolchain)
- UI change: check the pages in a real browser before you push, in light and dark themes, at desktop, tablet (about 820px), and phone width (browsers stop near 500px), when the session has a browser and the stack. Look for column widths, wrapping, horizontal scroll, and working menus. Fix what is off, then look again
- UI PR: attach screenshots of the important pages (desktop, plus tablet or phone) to the PR description. Take them in that browser pass on seeded data, never real names or emails. State in the PR body that the light/dark and tablet/mobile pass was done — or, when no browser pass was possible, that it was not done
- Before you push, run every Cypress spec the change touches (new and existing) against the seeded test stack when the session has one: `npm run docker:test:reset:db`, then `npx cypress run --config-file cypress/configs/docker.config.ts --spec "a.spec.js,b.spec.js"`. Report the pass counts. With a local stack, push CI is not where a spec first fails; without one, CI is the first run and the PR body says so
- Stage files by explicit path, never `git add -A` / `.`. Before commit, `git status --short` and `git diff --cached --name-status` must list only files the task expects; unstage or delete strays (editor/`sed -i` backups like `*-E`, generated files, symlinks) first
- The diff goes in the PR body, not in a pre-commit approval stop. Report the PR link after the push
- Title and body match the current diff
- Never `--force`. `--force-with-lease` only to push a rebase of your own already-pushed task branch
- Fixing an existing PR: push to that PR's head branch. A push to any other branch can auto-open a duplicate PR
- Keep unrelated fixes (a CI retry beside a security fix) in their own PR
- Do not merge `master` into a contributor branch or push to their branch unless asked
- Do not run `locale:build` or commit `messages.po` on a feature branch. Any locale command rewrites the `POT-Creation-Date` line of `locale/messages.po`: when that is the only change, `git checkout -- locale/messages.po` and never commit it

## Docker checkout

Do not switch the maintainer's checkout to the task branch for Docker work. Agents run Docker from their own worktree on its own Compose project. Switching the maintainer's checkout still needs a yes.

## Task approval covers commit and push

Approval attaches to the task, not to each git operation. When a maintainer assigns a task — hands over an issue or a prompt, or says run it — that approval covers, inside the task's scope:

- Creating the task branch, committing, pushing it, and opening a draft PR
- Pushing follow-up commits to that same branch: CI fixes, review-comment fixes, and merge-conflict fixes (rebase, then `--force-with-lease`)
- Pushing each independent PR as soon as its slice is ready, so CI and review start on it while the next slice is built

Do not stop to show a diff for a yes before committing, and do not wait for a message that says "push". The PR carries the diff; report after the push with the PR link, the validation results, and anything unresolved.

The checks before a push do not change — lint, build, and the touched specs above. They are gates the agent runs when its environment can, not maintainer taps, and never commit gates (see Sessions without a toolchain).

Push discipline, because every push runs the full GitHub CI matrix (about 15-20 billable minutes): commits are free and accumulate locally; a push is not a commit. Push once per finished slice, not per increment. After a push, wait for that CI run to finish, collect every CI failure and every open review thread, fix them in one batch, and make one follow-up push — never a push per fix, and never a new push while the previous run is still going.

Still needs a specific yes, every time:

- Merge, closing an issue by hand, a release, or publishing
- A push to `master` or another protected branch, a push to someone else's branch, or a plain `--force`
- Anything outside the assigned task's scope — a new issue, an unrelated fix, a scope expansion. Stop and ask there; that is where the decision is.

## Sessions without a toolchain

Mobile and cloud app sessions have no PHP, Node, Docker, database, or browser stack running. That changes where validation happens, never whether work proceeds:

- Commit freely. The only commit gates are the ones that need no runtime: the right branch, staging by explicit path, and the `git status` / staged-diff file list showing only the files the task expects.
- A validation step that needs a runtime — lint, a build, the touched Cypress specs, the browser pass — is not a commit block and not a reason to stop or ask. Do the review the environment allows (read the diff, check the syntax you can) and keep going.
- The PR body names each check that was not run locally and why (no PHP, no Node, no Docker), so CI is the evidence for it. Never report a check as passed when it did not run.
- For those checks, CI is the first run — the local-first rule in the pre-push checkpoint below applies only where a local run is possible. The browser pass and screenshots are the same: state in the PR body that they were not done, never claim them.

## Mandatory Pre-Push Biome Check

`.githooks/pre-push` runs `npm run lint`. `package.json` `prepare` sets `core.hooksPath=.githooks`. No-op when `$CI` or `$GITHUB_ACTIONS` is set.

Agents run `npm run lint` themselves before pushing when the session has Node. In a session without one, the hook cannot run either: name lint as not run in the PR body and let the `code-quality.yml` CI job be that push's lint run. Never `git push --no-verify` unless the maintainer authorizes a hotfix and the PR names the bypass.

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

`npm run lint` runs every check in parallel. The pre-push hook and the `code-quality.yml` job call it and nothing else.

- Add a check as one entry in the `checks` map in `scripts/lint/lint.js`. Do not add `lint:*` or `validate:*` npm scripts, and do not add a separate CI step for it. `lint:fix` is the only other lint script.
- A check must take a few seconds, need no Docker, database, or network, and print its findings to stdout or stderr with a non-zero exit.
- The pre-commit hook runs only checks scoped to staged files (`locale-check --staged`, the changed-file PHP validators). Whole-repo checks go in `scripts/lint/lint.js`.
- A check that always exits 0 is not a check. Test a new one against a bad input before you add it.
- `pr gates` (`scripts/lint/validate-pr-gates.js`) mirrors the CodeRabbit pre-merge checks: it fails a schema change without an upgrade script and untrusted context in a workflow `run:`, and prints notes for a behavior change without a spec, a workflow without a `tests/tooling/` test, and the UI pass. Add a rule there when a review keeps asking for the same thing.

## After push

If the PR work used a `git worktree` and the session has been idle 10 minutes after the push (no review threads or CI you were asked to watch), ask once whether to remove it (`git worktree remove <path>`, then delete the local branch if merged). Never remove it unasked.

Do not approve or merge. Do not close an issue by hand unless the maintainer answers yes. A finished issue is closed by `Fixes #N` on the merged PR.

A push that fixes review comments is not done until the threads it fixed are resolved. Check each thread against the pushed code first, then resolve it (`resolveReviewThread` in the GraphQL API). Reply with the commit SHA when the fix is not obvious. Leave a thread open, with a reply, when it is not fixed or you disagree. List each unresolved thread when you report back.
