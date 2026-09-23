# /pr-review

Review a ChurchCRM pull request using the maintainer gates.

**Arguments:** a PR number or URL. If omitted, use the PR already in context.

Read in order:

1. `.agents/skills/churchcrm/maintainer-review-gates.md`
2. `.agents/skills/churchcrm/pr-review.md`

## Rules

- Draft in chat first. Do not post until a maintainer says to post.
- Do not approve. Do not merge.
- Hard blocks → Request changes. Everything else → Comment.
- UI changes: require a tablet/mobile manual pass. Ask for screenshots if they exist. Do not block only because shots are missing.
- Dates, times, numbers, currency, timezone: follow `timezone-handling.md`. API/storage is ISO or naive `sTimeZone` wall-clock. Display settings are not parse formats. Wrong contract is Request changes.
- Posted reviews include a checkbox list of what is still open.
- Do not ask for `locale:build`.
- Be thankful. Do not nitpick.
