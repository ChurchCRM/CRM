---
title: Maintainer PR review gates
intent: Product and process gates for reviewing ChurchCRM PRs. Read this before churchcrm/pr-review.md. Agents never approve or merge.
tags: [pr, review, process]
---

# Maintainer PR review gates

Draft the GitHub review in chat as normal Markdown (the text that would be posted). Post only when a maintainer says to post; agents never approve or merge. Most reviews go out as the acting maintainer.

If the PR author is the same GitHub user the agent is acting as, do not post a review comment. Fix the branch instead.

Issue sizing and contributor readiness are defined in [`docs/contributing/issue-readiness.md`](../../../docs/contributing/issue-readiness.md). Use that guide for scope, epic decomposition, and `good first issue` readiness; this file remains authoritative for PR hard blocks and follow-ups.

Passing this checklist does not mean the PR should merge.

Leave CI status out of the review; branch protection owns it.

## Tone

The author is a volunteer. Be thankful, specific, and focused on what matters.

The review opens with one welcome sentence when it fits, then one short paragraph of what the PR does, then **Church-user impact** before any other heading:

- First pull request from this author on ChurchCRM/CRM: `Welcome to ChurchCRM, @author.`
- Their previous pull request on this repo was opened more than four weeks ago: `Welcome back, @author.`
- Anyone else: `Thanks @author.` with no welcome sentence.

Count every pull request they opened here, including closed ones. Skip the welcome on a re-review of the same author in the same week. The lookup is in `pr-review.md`.

## Comment Resolution

Read every review thread on the PR (inline threads and review summaries, from people and bots) before you review or report on it. Resolve a thread when the current PR code addresses it (question answered, bug fixed, design decision made). Leave it open, with a reply, when it is not addressed. A bot's `CHANGES_REQUESTED` review stays until that bot reviews again, so a green check does not mean approval: report the unresolved threads and each bot's latest review state.

## Review Format: No Hard Blocks

When a PR has no hard blocks, use this clear format instead of checkbox lists:

```markdown
Welcome to ChurchCRM, @author. One short paragraph of what the PR does.

**Church-user impact:** [role, job, what changes for them] or "no user-facing change: [why]".

**Verified:**
- ✓ [specific item]
- ✓ [specific item]
- ✓ RBAC: [entry point, who can reach it, the check that enforces it, change vs master] or "no RBAC impact: [why]"

**Design approval:** [RFC or design link and the maintainer who approved it] or "no architectural change".

No hard blocks.
```

The "Verified" + "No hard blocks" signal replaces an all-checked checkbox list.

## Hard blocks — Request changes

1. Security — XSS, injection, auth gaps, CSRF, open redirect, data leak, unsafe URL rendered to members.
2. Performance — extra queries or heavy work on login, people list, or other common paths, unless measured as fine.
3. Existing installs — required new fields with no default, surprise behavior, destructive migration, surprise permission change.
4. UI that breaks `frontend-development.md` (not Tabler / Bootstrap: a new static `style=""`, page `<style>` block, Bootstrap 4 class name, hard-coded light-theme colour, or a dropped `@media` rule), or a UI change with **no manual pass stated** in light and dark at desktop, tablet and phone. Missing screenshots alone is not a hard block if the author (or reviewer) will still do that pass. Tiny tweaks can follow up.
5. Localization wrap — new user-visible strings not in `gettext()` / `i18next.t()`. Translations and `locale:build` come after merge.
6. Locale-sensitive values — dates, times, numbers, currency, or timezone that ignore ChurchCRM conventions. See below.
7. Tests — feature or bug fix with no new or updated tests.
8. Repo process — issue rule in `git-workflow.md` not met; title or body that describes different work than the diff.
9. Query View freeze — any new feature or filter on `QueryView.php`, `QueryList.php`, or predefined `query_qry` / `queryparameters_qrp` rows for that UI. Point the author at Slim/Tabler MVC + Propel (`src/v2/`), or a reports plugin (`src/plugins/`; `src/plugins/community/` to share). Security-only patches on Query View need an explicit maintainer exception.
10. Playwright selectors are a PR gate, not a review item. `pr gates` in `npm run lint` fails the push when a removed element id is still selected under `playwright/`. A missing new capture stays a follow-up and is mentioned only when it passes the value test in `marketing-visuals-pipeline.md`.
11. RBAC impact — the PR adds or changes a route, API endpoint, middleware, permission or role check, session or sign-in flow, a query that returns another person's data, an admin setting, a plugin hook, or a UI control shown or hidden by role. For each changed entry point the review states who can reach it (anonymous, API key, member/portal session, staff user with which permission, admin), what the code enforces (name the middleware or check), and what changed versus `master` (privilege gained, lost, or newly reachable). Request changes on: a missing check; a check that trusts a client-supplied id; a role widened by default; a path from a lower session type to a higher one (portal to staff, API key to session); authority that outlives its revoked source; a low role that can disable or lock out a higher one. See `authorization-security.md`.
12. Architecture without an approved RFC — the PR adds a subsystem, a new table family, a new session or auth model, a plugin API, or a cross-cutting middleware, or changes a documented design. It links the design (an RFC in `docs/decisions/`, a doc in `docs/design/`, or the linked epic or issue) and a maintainer's approval of it (a comment, label, or the RFC's Status line). Without both, the review says so and asks that the design be approved first. Agents report whether approval exists and by whom; they never approve an RFC or judge it for the maintainer.
13. People and family status regression — the PR lists, exports, contacts, or acts on people or families and, versus `master`, newly includes deactivated families (`fam_DateDeactivated`) or deceased people (`per_DateDeceased`) in a surface that excluded them, or sends a communication (email, SMS, printed mailing) to deceased people or deactivated families with no explicit exclusion or maintainer decision. Deceased is a person-level fact and inactive is a family-level fact; a change that treats one as the other is a hard block under this item.

Comment volume is not a review topic. Wrong or essay comments can be deleted; that is not Request changes.

### Locale-sensitive values

Applies when the PR reads, writes, validates, or displays a date, time, number, currency, or timezone.

- API and storage dates are ISO `Y-m-d`, or naive `Y-m-d H:i:s` wall-clock in `sTimeZone`. See `timezone-handling.md`.
- Parse input as ISO or naive `sTimeZone` wall-clock. `sDateFormat*`, `sTimeZone`, and `sLanguage` drive **display** only.
- Store event DATETIME columns as naive wall-clock in `sTimeZone`.
- Keep one calendar contract per route (for example, space-separated datetime stays space-separated).

Report and PDF output reads the same settings (`reports.md` Data rules): dates through `DateTimeUtils::formatDate()`, money through `CurrencyFormatter`, labels through `gettext()`, all formatted in the service. A template or service that hardcodes a date format, currency symbol, separator, English word or database code is a hard block.

ISO-only validation on an API field is **correct**. Parsing `23/09/2026` because the church display format is `d/m/Y` is a hard block.

### People, family status, and scale

Applies when the PR lists, searches, exports, prints, contacts, or acts on people or families — including labels or names built from person queries (a family label assembled from its head and spouse is a people surface).

- **Families.** State how deactivated families (`family_fam.fam_DateDeactivated`) are treated: included, excluded, or included and marked. Match `master` unless the PR's issue says otherwise. Where staff act from the list (assigning, posting, mailing), an included inactive family must be visibly marked, or excludable through a filter, so staff know what they are looking at.
- **People.** Deceased (`person_per.per_DateDeceased`) is not inactive, and inactive is not deceased — never merge them into one flag. A name drawn from a person query (directory rows, family labels, recipient lists) must exclude the deceased or mark them where a staff member could otherwise act on the name by mistake.
- **State it.** Every review of a people- or family-touching PR carries a status line next to the RBAC statement: "Status: [inactive families — included / excluded / marked; deceased people — excluded / marked; change vs master]". Silence on status is an incomplete review.
- **Scale.** Judge the delivered list at real-church size: 200+ families on one screen, and households with many members. Data comes from bulk queries, never a query per row (hard block 2). The staff member must be able to work the list — search, sort, filter, pagination, or clear status markers; one unmarked wall of rows that grows with the church is a follow-up, and a list that breaks or times out at that size is a hard block under Performance.
- **Workflow, not just the diff.** Name the staff role and the job they are finishing on this screen (a treasurer assigning envelope numbers, a secretary updating the directory) and judge whether the delivered page lets them finish it knowing who is active, inactive, or deceased. A technically correct list that hides status fails the workflow even when it passes the code gates; report it as a follow-up with the concrete fix.

If hard blocks stay open and the author goes quiet, maintainers may close the PR for inactivity.

## Not blockers — Comment plus follow-up

- Demo-import values belong in `src/admin/demo/config.json`, which keeps the Cypress seed stable.
- User manual tracking issue on ChurchCRM/CRM. The docs pull request uses the same milestone and stays open until that release is published.
- Docs follow-up. A PR that adds or changes user-visible behavior gets one note in the review: the docs pull request on `ChurchCRM/docs.churchcrm.io` needs that release milestone. Suggest a screenshot or video only when it passes the value test in `marketing-visuals-pipeline.md`. The docs pull request merges only after the release exists, its CI is green, and CodeRabbit has approved. Skip bug-fix, security, CI, locale, dependency, and refactor-only PRs. Not a blocker.
- A behavior change with no Cypress spec gets a review note. A missing Playwright capture is never a review item; mention one only when the change passes the value test in `marketing-visuals-pipeline.md`. A removed id that a capture still selects fails `pr gates` before push.
- Status markers or a status filter missing where the PR's behavior matches `master` (the legacy surface did the same). Comment, plus a follow-up issue — a `good first issue` when it is one small, spec-able change (badges and a filter on one list), not a request-changes.
- Marketing / blog only on a full end-to-end feature. Ask George; skip bug/security-only.
- Member-facing consumer when this PR is storage-only.
- Extra screenshots. If they have tablet/mobile shots, ask them to attach.

## Review shape

1. Open with the welcome or thanks from Tone. Say what the PR does after reading the file list. Put **Church-user impact** next, before the findings.
2. Check title + body against the current file list.
3. Hard blocks first (Request changes). Every draft states RBAC impact and design approval, including "no RBAC impact: <why>".
4. Then a **checkbox list of what is still open** (never CI).
5. If there are no hard blocks, event is Comment.
6. Close only when the maintainer answers yes to a direct question.

## Draft format (chat + GitHub)

Write headings and lists. One line at the top for the maintainer only:

`Draft — Comment | Request changes. Title matches. Waiting for yes to post.`

Then the body that would go on GitHub:

**When no hard blocks:**

```markdown
Welcome to ChurchCRM, @author. One short paragraph of what the PR does.

**Church-user impact:** [role, job, what changes for them] or "no user-facing change: [why]".

**Verified:**
- ✓ [specific item]
- ✓ RBAC: [entry point, who can reach it, the check that enforces it, change vs master] or "no RBAC impact: [why]"

**Design approval:** [RFC or design link and the maintainer who approved it] or "no architectural change".

No hard blocks.
```

**When there are issues:**

```markdown
Welcome back, @author. One short paragraph of what the PR does.

**Church-user impact:** [role, job, what changes for them] or "no user-facing change: [why]".

## Hard blocks

- [specific issue that requires changes]

## RBAC impact

- [entry point: who can reach it, the check that enforces it, change vs master] or "no RBAC impact: [why]"

## Design approval

- [RFC or design link and approving maintainer] or "no architectural change" or "missing: approve the design first"

## Still open

- [ ] [specific item to verify]

## Not blocking

- [minor item or note]
```

If there is a hard block, post with "Request changes" event. Ask George in chat whether to post.
