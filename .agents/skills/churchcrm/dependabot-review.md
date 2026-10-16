---
title: "Dependabot PR Review"
intent: "Process for reviewing a Dependabot npm or Composer PR: read each upgrade's real changes, check how this repo uses them, and report a verdict"
tags: ["dependencies","dependabot","npm","composer","review"]
---

# Dependabot PR Review

Start with `/dependabot-review <PR number>`. This card is the process; package versions, changes and usage come from the PR and the repo each time.

Read the release notes for every package between the old and new version. A patch or minor release can change behavior, so the version number alone decides nothing.

## Manifest vs lock file

| Ecosystem | Range (manifest) | Exact install (lock) |
|---|---|---|
| npm | `package.json` | `package-lock.json` |
| Composer | `src/composer.json` | `src/composer.lock` |

- The manifest holds the allowed range (`^2.7.0` allows `>=2.7.0 <3.0.0`). The lock pins the exact version, and CI and Docker builds install from it, so the lock is what ships.
- Dependabot updates only the lock when the new version fits the range, and widens the manifest range for a new major. Check the range with `grep -n '"<package>"' <manifest>`.
- Raise a range floor only when the code needs a fix from that version.

## Steps

1. **Read the PR.** `gh pr view <N> --repo ChurchCRM/CRM --json title,body,files,headRefOid,baseRefName`. Save the body and diff to the scratchpad; grouped PRs have long bodies.
2. **Find the base.** `git fetch origin pull/<N>/head master`, then `git merge-base FETCH_HEAD origin/master`.
3. **Get the exact lock changes.** Compare the lock at the merge base with the lock at the PR head, by package name (`packages` in `package-lock.json`; `packages` and `packages-dev` in `composer.lock`). Include transitive packages. Then compare the PR head with the current `master` tip too: a PR built on an old base reverts anything `master` upgraded since, and that shows up only in this second comparison.
4. **Classify each package** as runtime or dev from the manifest (`dependencies` / `devDependencies`, `require` / `require-dev`). Runtime and dev updates belong in separate PRs (`.github/dependabot.yml` groups).
5. **Read the changes for each package.** Start with the release notes in the PR body; fill gaps with `gh api repos/<owner>/<repo>/releases/tags/<version>` or the changelog. Cover every release between the two versions.
6. **Map each package to its use in this repo.** Grep `src/` and `webpack/` (excluding `vendor/` and built `*.min.js`) for the API the package provides, and note the file paths.
7. **Check CI and committed output.** Which jobs run the package? Which jobs write files back to the repo or publish them? Regenerate and diff any committed output the bump can change.
8. **Check config and other PRs.**
   - A major listed under `ignore` in `.github/dependabot.yml` is a planned upgrade; name the entry in the report.
   - List open Dependabot PRs (`gh pr list --repo ChurchCRM/CRM --author app/dependabot --state open`) and report any that bump the same packages to an older version.
   - Check open alerts: `gh api "repos/ChurchCRM/CRM/dependabot/alerts?state=open"`. Say which ecosystem each alert belongs to.
9. **Verify.** Run what CI runs for the changed ecosystem, and record what ran:
   - npm: `npm ci`, `npm run build`, `npm run lint`, and the Cypress specs for the pages a runtime package touches (`cypress-testing.md`).
   - Composer: `cd src && composer install`, then `npm run build:php:validate:all` and `npm run test:php` from the repo root.
   - Any generator CI runs, with a diff of its output.
   A check that could not run is listed as not run, with the reason.
10. **Report.** Approve, merge, comment and push stay with the maintainer (`maintainer-review-gates.md`).

## Output format

- Verdict: `safe to merge` / `merge after <action>` / `do not merge`
- One row per package, transitive packages included: package, bump, runtime or dev, repo usage, risk, action
- Reverts against the current `master` tip
- Checks run, and checks not run with the reason
- Committed or generated files the PR changes
