---
title: CI and test infrastructure
intent: How the Cypress CI jobs, shards, test database and cleanup guard fit together, and how to triage a red run. Load this when CI fails, a spec is added or removed, or the schema or seed changes.
---

# CI and test infrastructure

Writing a spec: `cypress-testing.md`. Permission specs: `authz-gate-tests.md`. Workflow map: `docs/maintainers/github-actions.md`.

## Pipeline

`lint` (`npm run lint`) and `build` run first. Every test job needs both, so a red lint stops the Docker jobs. `build` uploads `ChurchCRM-<version>.zip`; `release-publish.yml` takes that zip from the green run on the release commit. `test-summary` gathers the results.

Pull requests run the suite at the site root. The nightly workflow runs it at the root and under `/churchcrm`, plus the setup wizard, the locale sweep and the upgrade matrix. Shard counts and matrix cells live in `.github/workflows/build-test-package.yml` and `build-test-nightly.yml`; read them there.

## Shards

`api`, `admin-ui` and `ui` split across shards by recorded duration. `scripts/shard-specs.js` reads `cypress/split-timings.json`, packs the longest specs first onto the lightest shard, and logs `shard N of M: …`.

- Each shard has its own Docker stack and database. Specs in a shard run one after another in path order.
- A spec's neighbours change when specs are added, removed or re-timed. A spec that depends on data another spec leaves behind passes in one split and fails in another. Fix the coupling in the spec; do not pin the split.
- A spec missing from the timings file gets the average duration. `node scripts/split-timings.js check` (part of `npm run lint`) fails on an entry for a spec that does not exist, a duplicate or an unsorted list.
- Refresh the timings from job logs: `gh run view --job <id> --log > job.log`, then `node scripts/split-timings.js update --area api|ui|ui-admin job.log`.

## Install path

A site can live under `/churchcrm`. `npm run lint` (`subdir-safe urls`) fails on a root-relative or `./` literal handed to `fetch`, `$.ajax`, `url:`, `location`, `href`, `src`, `action` or `header('Location')`. A line that is deliberately root-relative ends with `// subdir-ok: <reason>`. Build URLs from `SystemURLs::getRootPath()` or `window.CRM.root`.

`cypress/e2e/ui/navigation/install-path-requests.spec.js` loads every page and fails on a same-origin request outside the install path. Bugs in server-built URLs, `.htaccess` or cookie paths show up in the nightly subdirectory run.

## Test database

The test database is built from two files, mounted in order by `docker/docker-compose*.yaml`: `src/mysql/install/Install.sql` (`00-install.sql`), then `cypress/data/seed.sql` (`10-seed.sql`). `npm run docker:test:reset:db` (`scripts/reset-test-db.js`) drops the database and loads both again. The `new-system` job starts empty and installs through the wizard.

`seed.sql` is test data only. `npm run lint` (`seed data only`) fails on a `CREATE TABLE` for an installed table or an `INSERT` without a column list.

- A schema change edits `Install.sql` and the upgrade script, never the seed. `db-schema-migration.md` covers dropped columns.
- The seed keeps the `DELETE FROM` lines for tables `Install.sql` pre-fills, the custom-field and `groupprop_N` tables the app creates at run time, and an `ALTER TABLE … AUTO_INCREMENT` per table that needs it. `family_custom` and `person_custom` hold rows past the last family and person; a counter left at max+1 hands a new family an id those tables already hold, and every spec that creates a family then fails.
- `startNewRelease.js` appends the new version to the seed's `version_ver` insert at release start.
- Specs read seeded records by their documented ids and names. A spec that needs a record it changes creates its own.

## Cleanup guard

`cypress/support/e2e.js` counts rows per table before and after every spec file and fails the file when rows grew or shrank. `note_nte` growth is reported, not failed. A spec that cannot restore a table calls `cy.allowRowDrift(table, maxDelta, reason)`.

- Delete what the spec creates, in `after`, through the API. A failing cleanup assertion is a finding.
- A closed deposit refuses to delete its payments. Reopen it first: `POST /api/deposits/{id}` with `depositClosed: false`, then `DELETE`.
- Payments left on a seeded family replace the delete controls on `SelectDelete.php` with the donations list, which fails any later spec that deletes that family.
- A "row counts changed" failure follows another failure in the same spec file. Find the first failure.

## Triage a red run

1. Open the run summary. `test-summary` always passes; its table lists every job's result and the Cypress artifacts. Each Cypress job's own summary lists its failed tests, and the job log prints `❌ <suite> <test>` with the assertion. `cypress-junit-*` holds the XML.
2. A cloud agent reading logs through an API gets one very long line. Save it to a file, split on `\n`, strip ANSI codes, and grep `❌`, `AssertionError` and `CypressError`.
3. `Expected '/FamilyEditor.php' to include '/people/family/'` means the save failed, not the navigation. The PHP log is in the `cypress-artifacts-…` artifact (kept one day, with screenshots, videos and Docker logs) under `cypress/logs/php/`.
4. A red `test-new-system` that restores a database has a restore file problem: the restore page needs a full backup, so the spec restores one the app made.
5. A job that fails before any test body ran (checkout, install, runner loss) is the only case for a re-run. A failing test is never a flake until its cause is found.

## Verify without Docker

A cloud agent has no Docker, and Composer cannot download GitHub archives. For schema or seed changes:

1. `apt-get install mariadb-server` (the test image is `mariadb:10.11`), start `mariadbd`, and create scratch databases.
2. Load `Install.sql`, then `seed.sql`, into one. Load the previous seed into another.
3. Compare `information_schema.columns`, `statistics` and `tables.auto_increment` between them, and diff `mariadb-dump --no-create-info --complete-insert --order-by-primary` output.

Then say in the PR that CI is the first run of the specs.

## Pushing during CI

A push restarts every job. Batch fixes into one push. Hold optional review nits until the run settles, unless the run is still in its build step.
