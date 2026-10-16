---
title: "Dependabot PR Review (Composer)"
intent: "Process for reviewing a Dependabot Composer PR: read each upgrade's real changes, check how this repo uses them, and report a verdict"
tags: ["dependencies","dependabot","composer","review"]
---

# Dependabot PR Review (Composer)

Start with `/dependabot-review <PR number>`. This card is the process only. It does not record any package's version, changes, or usage, because those change with every PR.

**Do not trust the version number.** A patch or minor release can change behavior. Read the release notes for each package between the old and new version.

## Lock file vs `composer.json`

- `src/composer.json` holds the allowed range (`^2.7.0` means `>=2.7.0 <3.0.0`). It is the contract with other projects and with humans.
- `src/composer.lock` pins the exact version installed. CI and Docker builds install from the lock, so the lock is what ships.
- Dependabot changes only the lock when the new version already fits the range. It changes `composer.json` only when the range must widen (a new major). Check first: `grep -n '"<package>"' src/composer.json`.
- Changing a range is a deliberate choice. Raise a floor only when the code needs a fix from that version.

## Steps

1. **Read the PR.** `gh pr view <N> --repo ChurchCRM/CRM --json title,body,files,headRefOid,baseRefName`. Save the body and diff to the scratchpad, because grouped PRs have long bodies. `gh` does not expose the base commit, so get it from the merge base: `git fetch origin pull/<N>/head master` then `git merge-base FETCH_HEAD origin/master`.
2. **Get the exact lock changes.** Compare the PR's lock with the merge base (where the PR branched), not the current tip of `master`, which may have moved since. Compare `src/composer.lock` at the merge base with the same file at the PR head, by name in `packages` and `packages-dev`. Then also compare against the current `master` tip: a PR built on an old base can silently revert newer versions on master. Include transitive packages; grouped PRs move them too.
3. **Read the changes for each package.** Start with the release notes in the PR body. For anything missing, use `gh api repos/<owner>/<repo>/releases/tags/<version>` or the changelog file. Read every release between the two versions.
4. **Map each package to its use in this repo.** Grep `src/` (excluding `vendor/`) for the public API the package provides. Note the file paths in the review. Do not change vendored code.
5. **Check CI and committed output.** For each package, does a CI job run it? Does any job write files back to the repo or publish them? Regenerate and diff any committed output the bump could change, before merge.
6. **Check Dependabot alerts.** `gh api "repos/ChurchCRM/CRM/dependabot/alerts?state=open"`. Alerts for npm packages are not fixed by a Composer PR; say so.
6a. **Find superseded PRs.** List open Dependabot PRs (`gh pr list --repo ChurchCRM/CRM --author app/dependabot --state open`). For each package in this PR, any other open PR that bumps the same package to an older version is superseded. Report each one with its number and the version it would install. A PR that is superseded by a newer one should be closed. Do not close PRs unless the maintainer says so, because closing a Dependabot PR stops it from reopening that version.
7. **Verify when possible.** PHP 8.4 and Composer are needed. Without them, say the checks were not run. Do not report them as passed.
   - `cd src && composer install`, then `cd .. && npm run build:php:validate:all && npm run test:php`
   - Run any generator that CI runs, and diff its output.
8. **Report.** One table per PR: package, bump, change type, repo usage, risk, action. Then a verdict. Do not approve, merge, comment on, or push to the PR unless asked (`git-workflow.md`, `maintainer-review-gates.md`).

## Output format

- Verdict: `safe to merge` / `merge after <action>` / `do not merge`
- The table above, one row per package, including transitive packages that changed
- What was not verified, and why
- Any committed or generated files that the PR would change

## Test policy

The repo does not use PHPUnit. PHP checks are plain scripts: `scripts/test-*.php`, run by `npm run test:php`.

- Do not add `phpunit/phpunit` to `composer.json` or `composer.lock`, and do not add PHPUnit-style test classes or `*Test.php` suites.
- A PR that does either gets a review comment asking for a `scripts/test-*.php` check or a Cypress spec instead (`cypress-testing.md`).
- A Dependabot PR never adds PHPUnit. If one does, stop and flag it.
