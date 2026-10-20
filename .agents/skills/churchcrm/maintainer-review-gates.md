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

## Comment Resolution

Read every review thread on the PR (inline threads and review summaries, from people and bots) before you review or report on it. Resolve a thread when the current PR code addresses it (question answered, bug fixed, design decision made). Leave it open, with a reply, when it is not addressed. A bot's `CHANGES_REQUESTED` review stays until that bot reviews again, so a green check does not mean approval: report the unresolved threads and each bot's latest review state.

## Review Format: No Hard Blocks

When a PR has no hard blocks, use this clear format instead of checkbox lists:

```markdown
Thanks @author. One short paragraph of what the PR does.

**Verified:**
- ✓ [specific item]
- ✓ [specific item]
- ✓ [specific item]

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
10. Existing Playwright captures — the PR removes, renames or reroutes something that `playwright/` specs, videos or helpers use (an id, route, setting, button, menu entry or aria-label) and does not update them. The capture suite runs on `master` after merge and feeds the release docs, so the break appears late. Same standard as a Cypress selector. Grep `playwright/` for what the diff removes; a capture marked `usedIn` must still exist (`npm run lint`). Locators must not depend on English text (captures run in 8 locales; videos run in `en` only).

Comment volume is not a review topic. Wrong or essay comments can be deleted; that is not Request changes.

### Locale-sensitive values

Applies when the PR reads, writes, validates, or displays a date, time, number, currency, or timezone.

- API and storage dates are ISO `Y-m-d`, or naive `Y-m-d H:i:s` wall-clock in `sTimeZone`. See `timezone-handling.md`.
- Parse input as ISO or naive `sTimeZone` wall-clock. `sDateFormat*`, `sTimeZone`, and `sLanguage` drive **display** only.
- Store event DATETIME columns as naive wall-clock in `sTimeZone`.
- Keep one calendar contract per route (for example, space-separated datetime stays space-separated).

ISO-only validation on an API field is **correct**. Parsing `23/09/2026` because the church display format is `d/m/Y` is a hard block.

If hard blocks stay open and the author goes quiet, maintainers may close the PR for inactivity.

## Not blockers — Comment plus follow-up

- Demo-import values belong in `src/admin/demo/config.json`, which keeps the Cypress seed stable.
- User manual tracking issue on ChurchCRM/CRM. The docs pull request uses the same milestone and stays open until that release is published.
- Docs and screenshots follow-up. A PR that adds or changes user-visible behavior gets one note in the review: the docs pull request on `ChurchCRM/docs.churchcrm.io` needs that release milestone, and screenshots or video for docs, blog, and marketing still have to land. Name which ones the PR already carries and which still need an issue. The docs pull request merges only after the release exists, its CI is green, and CodeRabbit has approved. Skip bug-fix, security, CI, locale, dependency, and refactor-only PRs. Not a blocker.
- A new Playwright capture when the PR changes UI that belongs on a marketing or docs page (breaking an existing capture is a hard block, item 10). It ships in the same PR (the spec only; images are generated and published to cdn.churchcrm.io, not committed); ask the author to add it. If it cannot be taken yet, an issue linked from the PR is enough. Not a blocker.
- Marketing / blog only on a full end-to-end feature. Ask George; skip bug/security-only.
- Member-facing consumer when this PR is storage-only.
- Extra screenshots. If they have tablet/mobile shots, ask them to attach.

## Review shape

1. Thank the contributor. Say what the PR does after reading the file list.
2. Check title + body against the current file list.
3. Hard blocks first (Request changes).
4. Then a **checkbox list of what is still open** (never CI).
5. If there are no hard blocks, event is Comment.
6. Close only when the maintainer answers yes to a direct question.

## Draft format (chat + GitHub)

Write headings and lists. One line at the top for the maintainer only:

`Draft — Comment | Request changes. Title matches. Waiting for yes to post.`

Then the body that would go on GitHub:

**When no hard blocks:**

```markdown
Thanks @author. One short paragraph of what the PR does.

**Verified:**
- ✓ [specific item]
- ✓ [specific item]

No hard blocks.
```

**When there are issues:**

```markdown
Thanks @author. One short paragraph of what the PR does.

## Hard blocks

- [specific issue that requires changes]

## Still open

- [ ] [specific item to verify]

## Not blocking

- [minor item or note]
```

If there is a hard block, post with "Request changes" event. Ask George in chat whether to post.
