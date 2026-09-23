---
title: Maintainer PR review gates
intent: Product and process gates for reviewing ChurchCRM PRs. Read this before churchcrm/pr-review.md. Agents never approve or merge.
tags: [pr, review, process]
---

# Maintainer PR review gates

Draft the GitHub review in chat as **normal Markdown** (the same text we would post). Do not dump a label block. Do not post, approve, or merge until a maintainer says to post. Most reviews go out as DawoudIO.

Passing this checklist does not mean the PR should merge.

## Tone

The author is a volunteer. Be thankful. Be specific. Do not nitpick.

## Hard blocks — Request changes

1. Security — XSS, injection, auth gaps, CSRF, open redirect, data leak, unsafe URL rendered to members.
2. Performance — extra queries or heavy work on login, people list, or other common paths, unless measured as fine.
3. Existing installs — required new fields with no default, surprise behavior, destructive migration, surprise permission change.
4. UI that does not match Tabler / Bootstrap 5, or a UI change with **no manual tablet/mobile pass stated**. Missing screenshots alone is not a hard block if the author (or reviewer) will still do that pass. Tiny tweaks can follow up.
5. Localization wrap — new user-visible strings not in `gettext()` / `i18next.t()`. Do not require translations or `locale:build` in the feature PR.
6. Tests — feature or bug fix with no new or updated tests.
7. Repo process — no linked issue; lint/build clearly failing; title or body that describes different work than the diff.

If hard blocks stay open and the author goes quiet, maintainers may close the PR for inactivity. Do not name a timeline.

## Not blockers — Comment plus follow-up

- Demo-import in `src/admin/demo/config.json`. Never Cypress seed if that would break tests.
- User manual tracking issue on ChurchCRM/CRM. Docs PRs merge after the release ships.
- Marketing / blog only on a full end-to-end feature. Ask George. Skip bug/security-only.
- Member-facing consumer when this PR is storage-only.
- Extra screenshots. If they have tablet/mobile shots, ask them to attach. Do not Request changes only because shots are missing.

## Review shape

1. Thank the contributor. Say what the PR does after reading the file list.
2. Check title + body against the current file list.
3. Hard blocks first (Request changes).
4. Then a **checkbox list of what is still open**.
5. If there are no hard blocks, event is Comment.
6. Never approve. Never merge. Never close unless the maintainer answers yes.

## Draft format (chat + GitHub)

Write headings and lists. One line at the top for the maintainer only:

`Draft — Comment | Request changes. Title matches. Do not post until yes.`

Then the body that would go on GitHub:

```markdown
Thanks @author. One short paragraph of what the PR does.

## Still open

- [ ] …

## Not blocking

- …
```

If there is a hard block, say so in a **Hard blocks** heading before **Still open**. Ask George in chat whether to post. Do not approve.
