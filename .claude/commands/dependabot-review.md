# /dependabot-review

Review a Dependabot Composer PR on ChurchCRM/CRM: what each upgrade changes, whether it is compatible, what to verify, and whether we need code changes. Reads the real release notes and does not trust the version number.

**Arguments:** the PR number, for example `/dependabot-review 10553`

Follow [`.agents/skills/churchcrm/dependabot-review.md`](../../.agents/skills/churchcrm/dependabot-review.md) exactly.

## Mandatory rules

- Read the release notes for every package and every version between the old and new versions. Do not decide from the semver.
- Explain why the lock file changes and the `composer.json` range does not, unless the range must widen.
- Do not add PHPUnit or PHPUnit-style test suites. This repo uses `scripts/test-*.php` and Cypress.
- Do not approve, merge, comment on, or push to the PR. Report the verdict only.
- If PHP or Composer is not installed, say that the checks were not run. Do not report them as passed.
