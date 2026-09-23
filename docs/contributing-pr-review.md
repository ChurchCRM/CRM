# What maintainers look for before a ChurchCRM PR is merged

Use this **before opening a PR**. Matching it does not guarantee merge. Final approve and merge are always a human maintainer decision. We may decline a technically fine PR if we disagree with the goal or timing.

Thank you for donating time (and often tokens). We try not to nitpick. We will say so when something is a hard block versus a follow-up.

## Before you open the PR

1. Link an open issue. PRs without an issue are rejected.
2. Run lint and build.
3. Add or update tests for the change.
4. Wrap every new user-visible string in `gettext()` (PHP) or `i18next.t()` (JS). Translations happen outside this PR. `locale:build` runs on merge to `master`.
5. If you change the UI in a non-trivial way, attach screenshots (or a short recording) for desktop, tablet, and mobile sizes we support, and keep the Tabler / Bootstrap 5 look.
6. Think about churches already running ChurchCRM. New config must default safely. Do not change behavior by surprise. If the database or permissions change, include a safe upgrade path.
7. Do not add work that is likely to slow a common path (login, people list, Sunday workflow) without measuring or fixing it.
8. Do not introduce security issues (XSS, injection, auth bypass, open redirects, leaking member data).
9. Keep the PR **title and description true to the current diff**. If the work grows, update them.

## Hard blocks (expect Request changes)

These must be fixed in the same PR:

- Security problem
- Performance risk that is not measured as acceptable
- Unsafe upgrade or surprise behavior for existing installs
- Non-trivial UI change with no desktop / tablet / mobile proof
- New user-visible strings not wrapped for translation
- No tests for a feature or bug fix
- Title or description that describes different work than the files in the PR

If those stay open and the PR goes quiet, maintainers may close it for inactivity or finish the work themselves when the direction is good. We do not publish a deadline.

## Not merge blockers (expect a Comment + follow-up issue)

- Demo-import data (`src/admin/demo/config.json`) so the public demo can show the feature. Do **not** put filled optional demo values in Cypress seed if that would break tests.
- User manual work. Maintainers open a tracking issue on **ChurchCRM/CRM** even though docs live on docs.churchcrm.io. Docs PRs merge only after that release ships.
- Blog or marketing. Only a maintainer decides if something is campaign-worthy. Bug fixes and security-only PRs skip this.
- A later member-facing consumer, if this PR only stores and previews data in admin. Say that clearly in the PR body.

## Feature shape

A setting or helper with no member-facing consumer can ship if the admin path works. Call it storage-only in the PR and expect a follow-up issue for the consumer.

Bug fixes and security-only PRs do not need demo data, marketing screenshots, docs campaigns, or blog writeups. They still need tests, safe upgrades, and translation wrappers if they add UI copy.
