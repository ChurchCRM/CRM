---
title: Maintainer PR review gates
intent: Product and process gates for reviewing ChurchCRM PRs. Read this before churchcrm/pr-review.md. Agents never approve or merge.
tags: [pr, review, process]
---

# Maintainer PR review gates

Use this whenever you review a ChurchCRM PR. Then use `pr-review.md` for the code-standards checklist.

Draft the GitHub review in chat. Do not post, approve, or merge until a maintainer says to post. Most reviews go out as DawoudIO.

Passing this checklist does not mean the PR should merge. Maintainers can still reject the goal or timing.

## Tone

The author is a volunteer spending time and often tokens. Be thankful. Be specific. Do not nitpick. Skip style or refactor comments unless they pay for themselves.

## Hard blocks — Request changes

Fix in this PR:

1. Security — XSS, injection, auth gaps, CSRF, open redirect, data leak, unsafe URL rendered to members.
2. Performance — extra queries, N+1, heavy work on login, people list, or other common paths, unless measured as fine.
3. Existing installs — required new fields with no default, surprise behavior change, destructive migration, surprise permission change.
4. Non-trivial UI — no screenshots or recording covering supported desktop, tablet, and mobile, or it does not match Tabler / Bootstrap 5 UX. Tiny tweaks can follow up.
5. Localization wrap — new user-visible strings not in `gettext()` (PHP) or `i18next.t()` (JS). Do not require completed translations or locale dumps in the feature PR. `locale:build` runs on merge to `master`.
6. Tests — feature or bug fix with no new or updated tests.
7. Repo process — no linked issue; lint/build clearly failing.

If hard blocks stay open and the author goes quiet, maintainers may close the PR for inactivity or finish it when the direction is good. You may say that old PRs with blocking issues may be closed for inactivity. Do not name a timeline.

## Not blockers — Comment plus follow-up issue

Open tracking issues on ChurchCRM/CRM (not the docs repo) after asking before posting:

- Demo-import data in `src/admin/demo/config.json` when the feature is worth showing. Do not fill Cypress seed if that would break tests. Never block merge.
- User manual when a non-technical user could get stuck or the workflow is complex. Docs PRs merge only after that release ships.
- Marketing or blog only on a full end-to-end feature. Flag ask George if this is a campaign item. Skip for bug fixes and security-only PRs. Never block merge.
- Member-facing consumer when this PR is storage-only (admin edit/preview). Say so. Open a follow-up. Do not block merge.

## Review shape

1. Thank the contributor. Say what the PR does in one short paragraph.
2. Hard blocks first. These are Request changes.
3. Follow-ups second.
4. If there are no hard blocks, GitHub event is Comment. A human decides approve/merge later.
5. Never approve. Never merge.

## Output in chat before any GitHub post

```
Post as: DawoudIO (ask before posting)
Event: REQUEST_CHANGES | COMMENT
Summary: …
Hard blocks: …
Follow-ups to open: …
Marketing flag for George: yes/no
Do not approve.
```
