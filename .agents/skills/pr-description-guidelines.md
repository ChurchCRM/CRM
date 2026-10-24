# PR title and description

Write for the human reviewer first. Lead with the goal and outcome; keep detail proportional to risk.

## Title

Use a short, specific title that matches the current diff.

## Body

Use `.github/PULL_REQUEST_TEMPLATE.md` as the structure.

- **Goal:** why this change matters.
- **Done:** the user-visible or technical outcome actually delivered.
- **Validation:** relevant tests/commands and results.
- **Risk:** migration, security, permissions, or compatibility details when they affect review.

Put the summary first. Keep routine PRs short; let the diff, tests, and linked issue provide detail. Add context only when the reviewer needs it to make a decision.

Describe positive outcomes. Include non-goals, alternatives, failure cases, or exhaustive negative-test lists only when they resolve a real scope, safety, or compatibility question. Do not repeat the file list, narrate obvious code, or claim behavior changes in docs-only PRs.
