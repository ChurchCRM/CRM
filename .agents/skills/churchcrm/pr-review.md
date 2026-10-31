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
gh api graphql -f query='{repository(owner:"ChurchCRM",name:"CRM"){pullRequest(number:<NUMBER>){reviewThreads(first:100){nodes{id isResolved isOutdated path line comments(first:20){nodes{author{login} body}}}} reviews(first:50){nodes{author{login} state submittedAt commit{abbreviatedOid}}}}}}'
```

`--comments` omits inline threads. The GraphQL call lists every thread (resolved or not, any author, including bots) and each review's state. Read all unresolved threads and check each against the current code.

Answer before reading code:

- What is the stated purpose?
- Bug fix, feature, refactor, or migration?
- Does another open PR already cover the same issue? Read its comments first: a maintainer may have a plan for it. Do not open a competing PR.
- Does it meet the issue rule in `git-workflow.md`? Missing it is a hard block.
- Does the goal belong in this milestone? Passing gates still does not mean merge if maintainers disagree with the goal.
- Does it add a subsystem, table family, auth model, plugin API or cross-cutting middleware, or change a documented design? Find the linked RFC or design (`docs/decisions/`, `docs/design/`, epic, issue) and the maintainer's approval. Report both; missing is a hard block (gates item 12).
- Does it extend Query View / predefined `query_qry` reports? If yes, Request changes. New reports are MVC + Propel or a plugin.

Leave the contributor branch as it is: merge `master`, resolve conflicts, or push only when the maintainer asks.

A feature or bug-fix PR without tests, or without a note of what the author ran, is not ours to test. A docs, CI, tooling, locale, dependency or refactor-only PR shows the validation that fits it (lint, build, the workflow run) instead of a new spec. Thank the author first. Say in one friendly paragraph that the tests keep the software safe and that the reviewer is not the PR's tester. Name the spec to copy (a spec in the same folder, `cypress-testing.md`) and offer to help. Hold the merge until the tests exist; a maintainer exception is the maintainer's call, in writing on the PR. Do not write or run the tests for the author unless the maintainer asks.

Credit the contributor. When their PR leaves the project better than before but has gaps, ask them to fix the gaps and explain why, so they learn and keep the credit. Do not replace their PR with ours. If they do not reply, open our own PR for the gaps (or merge theirs with a `Co-authored-by` trailer) and thank them there.

---

## Phase 2 — Review the full branch diff

Review `origin/master...<branch>`, not only the latest commit. After a rebase, preserve independent additions to shared test commands, check that the PR merges against the current base, and verify CI runs reference the latest head SHA.

```bash
git fetch origin
git diff origin/master...origin/<branch-name>
git log origin/master...origin/<branch-name> --oneline
git diff --name-status origin/master...origin/<branch-name>
```

A diff is not a review. For every changed function, route, or query:

- Read the whole function and the middleware/model it relies on, not only the hunk
- Grep every caller and every producer of the values the change branches on (types, flags, nullable columns); list what the new condition now rejects or accepts
- Check existing installs: legacy rows, NULL/empty values, demo and seed data, UI that still offers the action
- Verify each assertion in a new spec against the code (status codes, seed IDs, helper names)
- State in the draft what you read and what you did not; never mark an item verified from the diff alone

Look for:

- Comments added or changed in the diff: accurate against the code, and about why rather than what (`code-standards.md`)
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

### RBAC / authorization (hard block)

Applies to any change to a route, API, middleware, permission check, session, admin setting, plugin hook or role-gated control (gates item 11). Read `authorization-security.md`.

- [ ] Every new or changed route sits behind a named middleware
- [ ] The acting person or user comes from the session, never a request id
- [ ] Object ids in the request are checked against what the caller may see (`FamilyMiddleware`, `canViewFamily()`, `CustomFieldService::canView()`)
- [ ] API-key callers are refused or scoped on purpose
- [ ] A portal or member session cannot reach a staff route; no path raises one session type to another
- [ ] Admin-only settings and actions use the admin middleware
- [ ] Revoking a user, role or permission ends access on the next request
- [ ] Menu entries and buttons use the same permission as the route they open
- [ ] Cypress covers the denied user, not only the allowed one
- [ ] The draft names who can reach each entry point and what changed versus `master`

For a large auth PR, run a security-focused sub agent over the diff and verify its findings against the code before you report them.

### PDF / mPDF reports

Applies when a PR changes report data, rendering, templates, routes, exports or shared PDF helpers. Load `reports.md` and trace the complete call path against current master; include all merged consumers of a shared helper in the review scope.

- [ ] Discover renderer construction, wrappers, direct mPDF calls, downloads and email/portal consumers. Read current master and the branch; an open migration PR is not proof of merged behavior
- [ ] Check service/route/view/template/test placement, fixed template stems and their matching filenames, stable module URLs and download basenames. Resolve local assets through the document root and browser URLs through the install root; preserve supported APIs
- [ ] Check locale-sensitive display dates, currency, gettext phrases, Unicode/script fonts and RTL direction through the entire document. Keep storage/API dates ISO and template/file identifiers untranslated
- [ ] Verify escaping in the actual Twig environment, including injected environments, cached templates and shared partials. Trace intentionally formatted fields and test literal markup in names, addresses and memos; follow `reports.md` for response/CSV safety
- [ ] Preserve merged renderer helpers, page options, letterhead modes, photos and repeated-page behavior. Keep report-specific stock dimensions separate from ordinary page defaults
- [ ] Cover the persona workflow, accurate totals/grouping, empty/error filter preservation, allowed and denied callers, and supported output formats using the current test policy in `cypress-testing.md`
- [ ] State content-test, visual PDF, locale/RTL and physical-stock evidence separately. A valid PDF header or generated screenshot is not proof of layout or printer alignment; identify every unverified item before assessing merge readiness

The review states the affected consumers, enforced permissions, compatibility changes, tests actually run and remaining visual/physical checks. Keep inventories in the PR evidence; maintain reusable rules in the skill cards.

### Frontend & UI

Rules live in `frontend-development.md`; check the diff against it.

- [ ] Tabler/Bootstrap classes and tokens, no new static `style=""` or page `<style>` block, no root font-size, palette or theme-base override
- [ ] The page is built around a role's task (`frontend-development.md`): primary action in the page header, table above the fold, entry buttons land in the task
- [ ] Renders in **light and dark**, at **desktop, tablet and phone**: the author states the pass; screenshots if they exist
- [ ] Every `@media` rule survives the restyle; Cypress selectors the change touches still match
- [ ] `playwright/` specs, videos and helpers that use anything the change removes, renames or reroutes are updated in the same PR (grep `playwright/` for the ids, routes, settings and labels in the diff); locators carry no English text; captures marked `usedIn` still exist
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

Use the output block in `maintainer-review-gates.md`. The draft always contains the RBAC line and the design-approval line, even when the answer is "no RBAC impact: <why>" or "no architectural change".

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

- Check every unresolved thread, whoever wrote it, against the current code. Resolve each one the code now addresses (GitHub UI or GraphQL `resolveReviewThread`)
- Cite the commit SHA ("Fixed in abc123d")
- Leave a thread open when the fix is incomplete or the concern remains
- Report the threads still open and each bot's latest review state

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
