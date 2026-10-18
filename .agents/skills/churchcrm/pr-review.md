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

Use this skill when asked to review a PR or branch, check standards, or triage manual testing. Draft in chat and post only when a maintainer says to post. Approve and merge are human-only.

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
- Does it meet the issue rule in `git-workflow.md`? Missing it is a hard block.
- Does the goal belong in this milestone? Passing gates still does not mean merge if maintainers disagree with the goal.
- Does it extend Query View / predefined `query_qry` reports? If yes, Request changes. New reports are MVC + Propel or a plugin.

Leave the contributor branch as it is: merge `master`, resolve conflicts, or push only when the maintainer asks.

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

- Changes outside the stated purpose
- Debug leftovers
- Scope that belongs in a second PR
- Commit messages vs `git-workflow.md`
- `QueryView.php`, `QueryList.php`, or upgrade/seed edits that add predefined queries — hard block unless a maintainer excepted a security-only patch

---

## Phase 3 — Standards checklist

Apply only the sections that match the changed files. Hard-block items are also listed in `maintainer-review-gates.md`.

### PHP & Architecture

- [ ] PHP 8.4+ patterns only
- [ ] Explicit nullable params: `?int $param = null`
- [ ] `use` statements at top of file
- [ ] Helpers come from `ChurchCRM\\Utils\\*` (the `Functions.php` globals such as `\\MakeFYString()` and `\\FormatDate()` are deleted)
- [ ] ORM for DB work (no new `RunQuery()` or raw SQL)
- [ ] Query View stays frozen: no new features
- [ ] Dynamic IDs cast to `(int)`
- [ ] Object properties read as `$obj->prop`
- [ ] Services hold business logic
- [ ] Login, people list, and other common paths avoid N+1 and O(N×M) work
- [ ] Email failures logged, not thrown
- [ ] Logging uses `LoggerUtils::getAppLogger()`

### Security (hard block)

- [ ] `InputUtils::escapeHTML()` / `escapeAttribute()`
- [ ] Redirects use `RedirectUtils::redirect()`
- [ ] `SlimUtils::renderErrorJSON()` for API errors
- [ ] TLS verification on outbound HTTPS
- [ ] AuthZ on protected routes
- [ ] Free of injection, XSS, open redirect, and member-data leaks

### Frontend & UI

Rules live in `frontend-development.md`; check the diff against it.

- [ ] Tabler/Bootstrap classes and tokens, no new static `style=""` or page `<style>` block, no root font-size, palette or theme-base override
- [ ] The page is built around a role's task (`frontend-development.md`): primary action in the page header, table above the fold, entry buttons land in the task
- [ ] Renders in **light and dark**, at **desktop, tablet and phone**: the author states the pass; screenshots if they exist
- [ ] Every `@media` rule survives the restyle; Cypress selectors the change touches still match
- [ ] Asset paths use `SystemURLs::getRootPath()`
- [ ] UI text wrapped with `gettext()` or `i18next.t()`
- [ ] Messages and confirmations use `window.CRM.notify()` and bootbox or a Bootstrap modal
- [ ] Server-side initial state where a JS-only flash would show
- [ ] Page logic is a `webpack/` entry, not an inline `<script>` block in the view

### i18n

- [ ] New user-visible strings wrapped in `gettext()` / `i18next.t()` (hard block if missing)
- [ ] `npm run locale:build` and `messages.po` stay out of the PR (that job runs on every merge to `master`)
- [ ] Canonical terms: `People` not `Persons` in UI; `Active` / `Inactive` not `Deactivated`
- [ ] Verb+noun strings use a whole phrase or `sprintf(gettext('Delete %s'), …)`, not `gettext('Delete') . ' ' . gettext('Group')`
- [ ] Locale counts match the entries in `src/locale/locales.json`

### Database / existing installs (hard block if upgrade is unsafe)

- [ ] Schema change has a script under `src/mysql/upgrade/` and `upgrade.json`
- [ ] New columns nullable or defaulted
- [ ] ORM schema regenerated when the schema changed

### OpenAPI

- [ ] New or changed endpoints have `@OA\\` annotations
- [ ] Spec regenerated when annotations changed

### Testing (hard block if a feature or bug fix has none)

- [ ] Free of `console.log`, `var_dump`, `dd()`, `.skip`, `.only`
- [ ] New API endpoints have Cypress API coverage when that is the project pattern
- [ ] UI specs follow a role's workflow (`cypress-testing.md`): save, delete, filter, export and the denied user, not CRUD or page-renders
- [ ] Optional demo values stay out of the Cypress seed when they would break tests

### Git

- [ ] Free of commented-out blocks, debug files, and drive-by refactors

---

## Phase 4 — Docs, demo, marketing (not merge blockers)

| Change | Follow-up, not a merge gate |
|--------|-----------------------------|
| User-visible feature that could trip a non-technical user, or a complex workflow | Tracking issue on **ChurchCRM/CRM**. Open the docs pull request on `docs.churchcrm.io` with the same release milestone and leave it open. It merges only after that release is published, CI is green, and CodeRabbit has approved. |
| Demo-worthy feature | Follow-up to add `src/admin/demo/config.json` values |
| Full end-to-end feature | Flag "ask George if this is a campaign item". George decides. Skip for bug fixes and security-only PRs. |
| Storage-only admin setting | Say so. Open a consumer follow-up. |

The docs PR stays separate from the feature PR and waits for the release.

---

## Phase 5 — Manual validation

- Non-trivial UI: light and dark, at desktop, tablet and mobile
- Forms and error messages
- Unauthorized users cannot hit the new route
- English UI still reads correctly after new strings
- Migrations boot an existing-style database

---

## Phase 6 — Draft the review (do not post yet)

Use the output block in `maintainer-review-gates.md`.

- Hard blocks → `REQUEST_CHANGES`
- Follow-ups only → `COMMENT`
- Leave `--approve` and merge to maintainers
- Be thankful and keep to comments that pay for themselves
- You may say old PRs with blocking issues may be closed for inactivity, without naming a timeline

```bash
# Only after a maintainer says to post:
gh pr review <NUMBER> --request-changes --body "..."
gh pr review <NUMBER> --comment --body "..."
```

---

## Phase 6.5 — Resolve addressed review comments

**After posting a review or reviewing an author's fixes:**

- When the author has pushed fixes for your comments, resolve those threads (GitHub UI or GraphQL `resolveReviewThread`)
- Cite the commit SHA ("Fixed in abc123d")
- Leave a thread open when the fix is incomplete or the concern remains

---

## Phase 7 — Addressing review comments (author-side)

When implementing review feedback on a branch you were asked to fix:

1. Confirm each thread is still true on the current HEAD
2. Fix, show the diff, wait for push approval
3. After the approved push, resolve the threads that push fixed (`git-workflow.md` → After push)

---

## Phase 8 — Skills follow-up

If the review taught a durable rule, propose a skill edit in a follow-up. Skill edits land in their own follow-up.

Related: [maintainer-review-gates.md](./maintainer-review-gates.md), [git-workflow.md](./git-workflow.md), [github-interaction.md](./github-interaction.md), [code-standards.md](./code-standards.md), [i18n-localization.md](./i18n-localization.md), [frontend-development.md](./frontend-development.md).
