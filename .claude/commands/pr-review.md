# /pr-review

Review a ChurchCRM pull request using the maintainer gates.

**Arguments:** a PR number or URL (`9986`, `https://github.com/ChurchCRM/CRM/pull/9986`). If omitted, use the PR that is already in context.

Read these files now and follow them in order:

1. [`.agents/skills/churchcrm/maintainer-review-gates.md`](../../.agents/skills/churchcrm/maintainer-review-gates.md) — hard blocks vs follow-ups, tone, human-only approve/merge
2. [`.agents/skills/churchcrm/pr-review.md`](../../.agents/skills/churchcrm/pr-review.md) — how to fetch the PR and run the standards checklist

This command is a pointer, not a copy. Update the skill files when the process changes.

## Rules

- Draft the review in chat first. Do not post until a maintainer says to post.
- Do not approve. Do not merge.
- Passing the checklist does not mean the PR should merge.
- Hard blocks → Request changes. Everything else → Comment plus a follow-up issue.
- Do not ask for `locale:build` or a `messages.po` commit. That job runs on merge to `master`.
- Be thankful. Do not nitpick volunteer PRs.
