---
title: "PR Review"
intent: "Fetch a PR, apply maintainer gates, then run the code-standards checklist. Agents never approve or merge."
tags: ["pr","review","code-quality","standards","workflow"]
prereqs: ["[[maintainer-review-gates]]","[[code-standards]]","[[git-workflow]]","[[github-interaction]]"]
complexity: "intermediate"
---

# Skill: Pull Request Review

**Read [maintainer-review-gates.md](./maintainer-review-gates.md) first.** That file is the product/process source of truth: hard blocks vs follow-ups, human-only approve/merge, gettext wrap without locale dumps, docs issues on CRM, docs PRs after the release.

This file is the mechanics checklist (fetch the PR, code standards, how to draft a review). When the two disagree, the gates file wins.

Use this skill when asked to review a PR or branch, check standards, or triage manual testing. Draft in chat. Do not post until a maintainer says to post. Do not approve. Do not merge.

---

## Phase 1 — Understand the PR

```bash
gh pr view <NUMBER>
gh pr view <NUMBER> --comments
gh pr view <NUMBER> --json title,body,headRefName,baseRefName,state,latestReviews,reviews,comments
```

Answer before reading code:

- What is the stated purpose?
- Bug fix, feature, refactor, or migration?
- Is there a linked issue? PRs without an issue are a hard block.
- Does the goal belong in this milestone? Passing gates still does not mean merge if maintainers disagree with the goal.
- Does it extend Query View / predefined `query_qry` reports? If yes, Request changes. New reports are MVC + Propel or a plugin (#9995).

Do **not** merge `master` into a contributor branch, resolve their conflicts, or push to their branch unless the maintainer explicitly asks.

---

## Phase 2 — Review the full branch diff

Review `origin/master...<branch>`, not only the latest commit.

```bash
git fetch origin
git diff origin/master...origin/<branch-name>
git log origin/master...origin/<branch-name> --oneline
git diff --name-status origin/master...origin/<branch-name>
```

Look for:

- Changes unrelated to the stated purpose
- Debug leftovers
- Scope that should be a second PR
- Commit messages vs `git-workflow.md`
- `QueryView.php`, `QueryList.php`, or upgrade/seed edits that add predefined queries — hard block unless a maintainer excepted a security-only patch

---

## Phase 3 — Standards checklist

Apply only the sections that match the changed files. Hard-block items are also listed in `maintainer-review-gates.md`.

### PHP & Architecture

- [ ] PHP 8.4+ — no deprecated patterns
- [ ] Explicit nullable params: `?int $param = null`
- [ ] `use` statements at top of file
- [ ] No deleted `Functions.php` globals (`\\MakeFYString()`, `\\FormatDate()`, …) — use `ChurchCRM\\Utils\\*`
- [ ] ORM for DB work — no `RunQuery()` or raw SQL
- [ ] Do not add features to Query View (frozen; #9995)
- [ ] Dynamic IDs cast to `(int)`
- [ ] Object properties as `$obj->prop`, never `$obj['prop']`
- [ ] Services hold business logic
- [ ] No obvious N+1 or O(N×M) on login, people list, or other common paths
- [ ] Email failures logged, not thrown
- [ ] `LoggerUtils::getAppLogger()` — not `error_log()`

### Security (hard block)

- [ ] `InputUtils::escapeHTML()` / `escapeAttribute()`
- [ ] `RedirectUtils::redirect()` — not raw `header()`
- [ ] `SlimUtils::renderErrorJSON()` for API errors
- [ ] TLS verification on outbound HTTPS
- [ ] AuthZ on protected routes
- [ ] No injection, XSS, open redirect, or member-data leak

### Frontend & UI

Current stack is **Tabler + Bootstrap 5**. Do not reject Bootstrap 5 classes.

- [ ] BS5 / Tabler utilities (`ms-`/`me-`, `fw-bold`, `text-end`, `btn-close`, `data-bs-*`)
- [ ] Non-trivial UI has desktop, tablet, and mobile proof
- [ ] Asset paths use `SystemURLs::getRootPath()`
- [ ] UI text wrapped with `gettext()` or `i18next.t()`
- [ ] No `alert()` / `confirm()` — `window.CRM.notify()` and bootbox or a Bootstrap modal
- [ ] Server-side initial state where a JS-only flash would show

### i18n

- [ ] New user-visible strings wrapped in `gettext()` / `i18next.t()` (hard block if missing)
- [ ] Do **not** require `npm run locale:build` or a `messages.po` commit — that job runs on every merge to `master`
- [ ] Canonical terms: `People` not `Persons` in UI; `Active` / `Inactive` not `Deactivated`
- [ ] Do not invent new verb+noun concatenations (`gettext('Delete') . ' ' . gettext('Group')`) — use a whole phrase or `sprintf(gettext('Delete %s'), …)`
- [ ] ChurchCRM lists **49 locales** in `locales.json` — do not invent a different count

### Database / existing installs (hard block if upgrade is unsafe)

- [ ] Schema change has a script under `src/mysql/upgrade/` and `upgrade.json`
- [ ] New columns nullable or defaulted
- [ ] ORM schema regenerated when the schema changed

### OpenAPI

- [ ] New or changed endpoints have `@OA\\` annotations
- [ ] Spec regenerated when annotations changed

### Testing (hard block if a feature or bug fix has none)

- [ ] No `console.log`, `var_dump`, `dd()`, `.skip`, `.only`
- [ ] New API endpoints have Cypress API coverage when that is the project pattern
- [ ] Critical UI flows have Cypress UI coverage
- [ ] Do not put optional demo values in Cypress seed if that would break tests

### Git

- [ ] Linked issue
- [ ] No commented-out blocks, debug files, or drive-by refactors

---

## Phase 4 — Docs, demo, marketing (not merge blockers)

| Change | Follow-up, not a merge gate |
|--------|-----------------------------|
| User-visible feature that could trip a non-technical user, or a complex workflow | Tracking issue on **ChurchCRM/CRM**. Docs live in docs.churchcrm.io. Merge those PRs only after this release ships. |
| Demo-worthy feature | Follow-up to add `src/admin/demo/config.json` values |
| Full end-to-end feature | Flag "ask George if this is a campaign item". George decides. Skip for bug fixes and security-only PRs. |
| Storage-only admin setting | Say so. Open a consumer follow-up. |

Do not push docs.churchcrm.io to `main` as part of the feature PR.

---

## Phase 5 — Manual validation

- Non-trivial UI: desktop, tablet, and mobile
- Forms and error messages
- Unauthorized users cannot hit the new route
- English UI still reads correctly after new strings
- Migrations boot an existing-style database

---

## Phase 6 — Draft the review (do not post yet)

Use the output block in `maintainer-review-gates.md`.

- Hard blocks → `REQUEST_CHANGES`
- Follow-ups only → `COMMENT`
- Never `--approve`
- Never merge
- Be thankful. No nits that do not pay for themselves
- You may say old PRs with blocking issues may be closed for inactivity. Do not name a timeline

```bash
# Only after a maintainer says to post:
gh pr review <NUMBER> --request-changes --body "..."
gh pr review <NUMBER> --comment --body "..."
```

---

## Phase 7 — Addressing review comments (author-side)

When implementing review feedback on a branch you were asked to fix:

1. Confirm each thread is still true on the current HEAD
2. Fix, show the diff, wait for push approval
3. Do not resolve threads unless the maintainer asks

---

## Phase 8 — Skills follow-up

If the review taught a durable rule, propose a skill edit in a follow-up. Do not silently rewrite skills on a feature branch.

Related: [maintainer-review-gates.md](./maintainer-review-gates.md), [git-workflow.md](./git-workflow.md), [github-interaction.md](./github-interaction.md), [code-standards.md](./code-standards.md), [i18n-localization.md](./i18n-localization.md), [frontend-development.md](./frontend-development.md).
