# GitHub Actions

What each workflow in `.github/workflows/` does, what starts it, and what it starts next. The locale loop is explained in [locale-pipeline.md](locale-pipeline.md) and the release in [release-process.md](release-process.md); this page is the map that ties them together.

## Rules every workflow follows

- **No model calls.** Actions logs on a public repo are world-readable, and a model API key has to be handed to the job or action that calls it, where its steps, forked-PR runs and any leaked log could reach it. So AI agents (translation, reviews, release notes) run outside Actions with their own credentials. Workflows only do deterministic work.
- **Name the stage.** A workflow's display name is `<Area>: <what>` (`Locale: sync`). Its file name is `<area>-<what>.yml`. Rename the older names below when you next touch them.
- **Least privilege.** Every workflow except `marketing-capture-assets.yml` (see [Still to do](#still-to-do)) sets a top-level `permissions:` (default `contents: read`) and raises it per job only where needed.
- **One chain, no loops.** A workflow may start the next stage; nothing may start an earlier one. Triggers are scoped by `paths:` to what only the previous stage produces (see [Chains](#chains-and-why-they-do-not-loop)).
- **Settle bursts.** A run started by a merge waits 30 minutes and restarts the wait on each newer merge, so several merges become one run (`Locale: sync`).
- **Idempotent steps.** A re-run, a manual run and a scheduled run must be safe. A trigger decides *when* a workflow runs, not *what* it does.
- **Events from `GITHUB_TOKEN` do not chain.** A push, PR or merge made with `GITHUB_TOKEN` starts no other workflow (only `workflow_dispatch` / `repository_dispatch` do). One exception: `pull_request` events (`opened`, `synchronize`, `reopened`) on a PR that `GITHUB_TOKEN` opened or updated create runs that wait for approval instead of starting. Stages that must chain are started by a person's merge, a GitHub App, or an explicit `gh workflow run`.
- **Queue what publishes, cancel what is superseded.** Builds and checks cancel an older run when a newer one starts. `Locale: sync` cancels only its settle wait and queues the work itself. Release, bookkeeping, Docker publish and `Locale: upload translations` queue instead (`cancel-in-progress: false`).

## Branches

The prefix says who made the branch and what it is for.

| Branch | Made by | Purpose |
|---|---|---|
| `locale/terms/<version>-<YYYYMMDD-HHMMSS>` | `Locale: terms` | New source strings in `locale/messages.po`. Merged |
| `locale/download/<version>` | `Locale: sync` | What POEditor returned: translations and missing-term batches. Merged |
| `locale/translate/<version>-<YYYY-MM-DD>-<HHMMSS>` | translation agent | Translated batches. **Never merged**; the push is the upload |
| `build/<version>` | `Release: start next version` | The "Start `<version>` release" PR that bumps `package.json` |
| `release/**`, `hotfix/**` | maintainers | Build, test and nightly run on every push |
| `fix/issue-N-short`, `fix/short`, `ci/short`, `feature/short` | contributors | See `.agents/skills/churchcrm/git-workflow.md` |
| `dependabot/**` | Dependabot | Dependency bumps; `Dependabot: PR fact-check` comments on them |

## Workflows

### Build, test and quality

| Workflow (file) | Starts on | Does |
|---|---|---|
| `Build, Test and Package` (`build-test-package.yml`) | PR to `master`/`develop`/`hotfix/**`; push to those and `release/**`. Skips docs, locale terms and other non-code paths | Builds the PHP image, runs the Cypress suites (root and subdirectory installs, setup wizard) and packages `ChurchCRM-<version>.zip`. The release flow reuses that zip |
| `Build, Test and Package (Nightly)` (`build-test-nightly.yml`) | 03:00 UTC daily, manual, release published, push to `release/**` | The heavy sweeps: PHP 8.4 and 8.5, full locale sweep (root and subdirectory), upgrade tests |
| `CI — Typecheck, Lint & Format` (`code-quality.yml`) | push to `master`/`develop`/`hotfix/**`; PRs | `tsc`, Biome format and lint, `npm run locale:test`, seed charset, MySQL upgrade syntax, icon checks |
| `Documentation` (`docs.yml`) | push or PR touching API routes or `docs/openapi/**` | Generates and validates the OpenAPI specs; on `master` it commits the regenerated specs back (its own output paths are excluded from the trigger) |

### Security and Docker

| Workflow (file) | Starts on | Does |
|---|---|---|
| `Security: CodeQL` (`security-analysis.yml`) | push to `master`/`develop`, PR to `master`, Mondays 06:00 UTC, manual | CodeQL (JS/TS) and Semgrep (PHP/JS/config) |
| `Dependabot: PR fact-check` (`dependabot-review.yml`) | Dependabot PRs opened, updated or reopened; manual | One facts-only comment: packages bumped, major or minor, backing alert, transitive changes. Never approves, merges or labels |
| `Validate Docker development boundaries` (`docker-security-check.yml`) | PR touching the compose files | Checks the development compose files keep their security boundaries |
| `Validate production Docker images` (`docker-release-check.yml`) | PR touching `docker/**` or the docker release workflows | Archive validation and non-root startup of the production image |
| `Build & Push Docker Images` (`docker-release.yml`) | release published, manual | Downloads and verifies the published release, builds and pushes the image to DockerHub (`DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`) |

### Locale

| Workflow (file) | Starts on | Does | Needs |
|---|---|---|---|
| `Locale: terms` (`locale-generate-terms.yml`) | push to `master` touching `src/**` except `src/locale/i18n/**` and `src/locale/textdomain/**`; manual | Extracts new strings into `locale/messages.po` and opens the terms PR | — |
| `Locale: sync` (`locale-sync-poeditor.yml`) | push to `master` touching `locale/messages.po`, after a 30 minute settle that restarts on each new push; 00:00 UTC daily; manual; dispatched by `Locale: upload translations` with `upload_terms=false` (neither waits) | 1. upload terms, delete terms no longer in the file and verify POEditor matches, 2. download and open the download PR. No translation happens here. Runs from `master` only, because the terms upload deletes terms missing from the file | `POEDITOR_TOKEN` |
| `Locale: upload translations` (`locale-upload-missing.yml`) | push to `locale/translate/**` touching `locale/terms/missing/**`; manual | Uploads the locales that push changed, then starts `Locale: sync` download-only | `POEDITOR_TOKEN`, `actions: write` |

### Release

| Workflow (file) | Starts on | Does |
|---|---|---|
| `Release & Start Next` (`release-publish.yml`) | manual | Finds the latest green `master` build for the expected version, creates a **draft** release from its zip, writes the release-notes context artifact, then calls `Release: start next version` |
| `Release: start next version` (`release-prepare.yml`) | manual, or called by `Release & Start Next` | Bumps the version, pushes `build/<version>` and opens the "Start `<version>` release" PR |
| `Release: bookkeeping` (`release-bookkeeping.yml`) | release published, manual | Stops on a placeholder tag, syncs the changelog file and `CHANGELOG.md` table, advances the CRM release milestone, then syncs the docs repo's milestones (`DOCS_RELEASE_TOKEN`) |

Publishing the draft is a person's click. That `release: published` event then starts `Build & Push Docker Images`, `Release: bookkeeping` and the nightly upgrade tests.

### Repository upkeep

| Workflow (file) | Starts on | Does |
|---|---|---|
| `Issues: hygiene` (`issue-management.yml`) | issue opened, Mondays 02:00 UTC, manual | Security guard on new issues, stale marking, review of closed stale issues. After 45 quiet days it asks the reporter to reply (that keeps it open). No reply closes it 14 days later. Exempt: `Security`, `security-delete-required`, `Epic`, `good first issue`. At most 30 items per Monday. |
| `Issues: stamp milestone on merge` (`pr-milestone-stamp.yml`) | PR closed, manual | Puts a merged PR and its issues on the milestone matching the `package.json` version |
| `Marketing: capture screenshots & videos` (`marketing-capture-assets.yml`) | manual | Captures every locale's screenshots. On `master` it opens a PR when the committed visuals differ; on any other branch it fails if they are stale |

### Shared building blocks

Composite actions in `.github/actions/`: `checkout-node-npm` (Node from `.nvmrc`, cached `npm ci`), `composer-install` (PHP 8.4, cached Composer), `wait-for-server` (poll a URL), `run-cypress-tests` (Cypress with JUnit and debug artifacts).

## Chains and why they do not loop

```
Terms flow      PR ─merge─► master ─► Locale: terms ─► terms PR ─merge─► master
                  ─► Locale: sync (settle 30 min → upload terms → download) ─► download PR ─merge─► master

Translate flow  locale/terms/missing/ ─► translation agent (LLM, outside Actions) ─► locale/translate/* push
                  ─► Locale: upload translations ─► Locale: sync (download only) ─► download PR ─merge─► master

Release         Release & Start Next ─► draft release + build/<next> PR
                person publishes ─► Build & Push Docker Images · Release: bookkeeping · nightly upgrade tests
```

| Merge or event | Starts | Does not start | Why |
|---|---|---|---|
| terms PR merged | `Locale: sync`, after the 30 minute settle | `Locale: terms` | it touches `locale/messages.po`; `src/locale/i18n/**` is excluded from the terms filter |
| download PR merged | nothing | terms, sync | it has no `locale/messages.po`, and the terms filter ignores `src/locale/**` output |
| translate branch pushed | `Locale: upload translations`, once | anything on merge | the branch is never merged |
| `Documentation` commits specs | nothing | itself | commit made with `GITHUB_TOKEN`, and `docs/openapi/generated/**` is excluded |
| draft release created | nothing | docker, bookkeeping | `published` fires only when a person publishes |

## Still to do

Tracked in #10091:

- Merge the terms and download PRs automatically behind a validity check, with a GitHub App token so the next stage starts (#10093).
- Start the translation agent from the merged download PR (#10094). It must run outside Actions, or this page's first rule needs a decision.
- One READY / BLOCKED report before releasing (#10095).
- `Marketing: capture screenshots & videos` has no top-level `permissions:`; its `capture` and `merge` jobs inherit the repository default.
- Older display names that do not follow `<Area>: <what>`: `Build, Test and Package` (and Nightly), `CI — Typecheck, Lint & Format`, `Documentation`, `Release & Start Next`, `Validate …`, `Build & Push Docker Images`.
