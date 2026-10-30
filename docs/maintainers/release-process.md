# ChurchCRM Release Process

The maintainer runbook from localization sync to the marketing push. Follow the automation: workflows run what they own, and manual steps are approvals and judgment calls.

Legend: 🤖 automated · 🧑‍💻 agent-assisted · ✋ manual

## Checklist

1. **Locales clean:** `locale/terms/missing/` is empty on `master` (Phase 1). New strings follow the count-neutral rule in `i18n-localization.md`.
2. **Built and tested:** Build/Test/Package and the nightly are green on the same `master` HEAD, and the milestone for the `package.json` version is reviewed (Phase 2).
3. **Release & Start Next** with `next_version`, the only input (3.1). It requires green Build/Test/Package and Nightly on the branch tip, and refuses to replace an existing release. It starts the marketing capture, the notes context, the docs staging and the `Start <next> release` PR as separate jobs that never block the draft.
4. **Notes:** ask an agent to "write the `<version>` release notes". It reads the `release-notes-context-<version>` artifact and applies the approved text with `gh release edit <version> --tag <version> --notes-file …` (3.2).
5. **Open the `Start <next> release` PR, but merge it only after the release's marketing capture run has finished** (3.3).
6. **Check the tag, then Publish** (3.4): `gh release view <version> -R ChurchCRM/CRM --json tagName,isDraft` shows `"tagName":"<version>"`.
7. **After Publish** (Phase 4): bookkeeping `sync`, `manage-milestones` and `docs-release` are green, and the release nightly is green.
8. **Marketing** (Phase 6).

Recovery for a release published under a placeholder `untagged-<hash>` tag is in `MEMORY.md`; re-run `release-bookkeeping.yml` with `tag=<version>` afterwards.

---

## Hard gates and non-blocking automation

A **hard gate** stops the release until it is met or the maintainer overrides it. **Automation** runs beside the release and never holds up the draft or the Publish click; a failure is fixed and re-run afterwards.

| Step | Kind | Why |
|---|---|---|
| Build/Test/Package green on the tip, ZIP present (2.2) | Hard gate, enforced by `release-publish.yml` | The ZIP is the released file |
| Nightly green on the same tip (2.1) | Hard gate, enforced by `release-publish.yml` | Full suite and upgrade matrix on the exact commit |
| Version and tag sanity: `package.json` is `X.Y.Z`, `next_version` differs, no existing release or tag (3.1) | Hard gate, enforced by `release-publish.yml` | A wrong tag cannot be undone cleanly |
| Unfixed High-severity advisory (2.4) | Hard gate, maintainer override by GHSA ID | Do not ship a known High unannounced |
| Milestone review (2.3) | Maintainer judgment | Moves items that will not ship; not a workflow check |
| Release notes reviewed, `Start <next> release` PR merged, tag checked, Publish click (3.2 to 3.4) | Hard gate, maintainer approval | Bookkeeping reads the next version from `master` at publish time |
| Locales clean (Phase 1) | Checklist, not enforced | Missing translations fall back to English; the maintainer decides |
| Marketing capture | Automation, starts with the release | Visuals are not part of the shipped ZIP |
| Notes context, docs staging, `Start <next> release` PR creation (3.1) | Automation, separate jobs | The draft exists before they run; a failure is re-run |
| Changelog sync, milestones, docs release (4.4 to 4.6) | Automation after Publish, re-runnable | `release-bookkeeping.yml` with `tag=<version>` |
| Post-release nightly, advisory publish, Docker image check, docs PR review, marketing push (4.7 to 4.10, Phase 6) | After release | Verification and follow-up |

## Phase 1: Localization sync

Goal: every feature added this cycle is localized before release. The loop is **have terms → upload → re-download → translate → upload new terms → re-download clean**. The operator doc is [locale-pipeline.md](locale-pipeline.md).

Done when `npm run locale:translate:list` on `master` lists no locales (`locale/terms/missing/` empty) and no `Update locale strings` or POEditor download PR is open. A locale PR that opens after the maintainer closes this phase waits for the next release. Finish this phase before any build or nightly check: each locale merge moves `master` and voids earlier results. Write and approve the release notes (`changelog/<version>.md`) right after it, and merge any PR that carries them before Phase 2 for the same reason.

| # | Step | How | Status |
|---|------|-----|--------|
| 1.1 | `Locale: terms` extracts new strings on every merge to `master` touching `src/**` | [`locale-generate-terms.yml`](../../.github/workflows/locale-generate-terms.yml), about 4 min | 🤖 |
| 1.2 | Merge the `Update locale strings` PR it opens (branch `locale/terms/<version>-<timestamp>`, label `Localization`). No PR means no new strings | Review and merge | ✋ |
| 1.3 | Upload `locale/messages.po` to POEditor and delete terms that no longer exist | Merging the 1.2 PR changes `locale/messages.po` on `master`, which starts [`locale-sync-poeditor.yml`](../../.github/workflows/locale-sync-poeditor.yml). After a 30 minute settle (a newer merge restarts it), `poeditor-upload-terms.js` imports the terms with `sync_terms=1`, deletes terms missing from the file, verifies POEditor matches it, and refuses a deletion above 2% of the project | 🤖 |
| 1.4 | Run `Locale: sync` manually so the latest translations land before the release; it opens a `locale: update translations from POEditor - <date>` PR | `workflow_dispatch`, about 8 min | ✋ trigger / 🤖 run |
| 1.5 | Skim the POEditor PR's file list for unexpected files, then merge | The PR touches only `locale/`, `locale/terms/missing/<locale>/`, `src/locale/`, `src/locale/i18n/*.json` and `src/locale/textdomain/<locale>/LC_MESSAGES/*` | ✋ |
| 1.6 | Translate every missing term in `locale/terms/missing/<locale>/` with Claude, using church vocabulary | [Locale Translation skill](../../.agents/skills/churchcrm/locale-translation-workflow.md) and [`/locale-translate --all`](../../.claude/commands/locale-translate.md) on a fresh `locale/translate/<version>-<date>-<time>` branch, one commit and push per locale. Runtime scales with terms per locale: about 25 min for ~10 terms, about 3 h for ~155 | 🧑‍💻 |
| 1.7 | Upload the translated terms to POEditor | Every push to `locale/translate/**` runs [`locale-upload-missing.yml`](../../.github/workflows/locale-upload-missing.yml). Fallback: run that workflow manually (`locales` input), or `node locale/scripts/poeditor-upload-missing.js -y` with `POEDITOR_TOKEN` | 🤖 |
| 1.8 | `Locale: sync` downloads the new translations; scan and merge its PR (as 1.4 → 1.5) | The upload workflow dispatches the sync itself | 🤖 run / ✋ merge |

Notes:

- Translation pushes use the agent's own credentials; pushes made with the Actions `GITHUB_TOKEN` start no workflows. A PR merged with `GITHUB_TOKEN` fires no `push`, so merge terms and sync PRs as yourself or with a GitHub App.
- `Locale: sync` runs daily at 00:00 UTC and after every merged terms PR; the manual run in 1.4 only picks up translations made after the last run.
- `Locale: sync` uploads terms, then downloads; translated batches reach POEditor through `Locale: upload translations` (1.7).
- Plural handling and the terms upload guard: [locale-pipeline.md](locale-pipeline.md).

## Phase 2: Built and tested

Goal: the exact commit being released is built, fully tested, and its scope is clean.

**Exit checkpoint:**
- [ ] Phase 1 done, so HEAD does not move for locale PRs
- [ ] Latest green nightly SHA = `master` HEAD (2.1)
- [ ] Build/Test/Package green on `master` HEAD, ZIP artifact present (2.2)
- [ ] Milestone `<version>` has no open issue or PR that should ship (2.3)
- [ ] Every draft High-severity GHSA is fixed on the release commit, or the maintainer has overridden it by ID (2.4)

| # | Step | How | Status |
|---|------|-----|--------|
| 2.1 | Compare the latest successful nightly run's SHA with `master` HEAD. If they differ, run the nightly manually and wait for green | [`build-test-nightly.yml`](../../.github/workflows/build-test-nightly.yml): build, root and subdir suites, setup wizard, locale smoke, upgrade matrix. A manual run takes about 30 to 48 min | ✋ check and trigger / 🤖 run |
| 2.2 | Confirm `Build, Test and Package` on `master` HEAD is completed and green; its `ChurchCRM-<version>.zip` artifact is the released file | [`build-test-package.yml`](../../.github/workflows/build-test-package.yml) runs on every push to `master`, about 25 min; its `build` job uploads the ZIP | 🤖 run / ✋ check |
| 2.3 | Review the GitHub milestone matching the `package.json` version on `master`; remove any open issue or PR that will not ship | Milestones → `<version>`. [`pr-milestone-stamp.yml`](../../.github/workflows/pr-milestone-stamp.yml) stamps merged PRs and `release-bookkeeping.yml` rolls open items to the next milestone, so the review looks for blockers. Go through the open items one at a time and ask the maintainer for the target of each. The next releases are the next patch and the next minor (for example 7.8.3 and 7.9.0). A docs tracking issue stays in the release milestone that matches its feature's commit: check `ChurchCRM/docs.churchcrm.io` for an open or merged PR for it. A merged docs PR means the issue is finished, so ask to close it. An open docs PR keeps the issue where it is, because it can still finish before the `Start <next> release` PR merges. The same holds for any item whose remaining half is a PR in another repo (`ChurchCRM/visuals`, the website): it stays while that PR is open and can finish before then. Marketing capture issues (label `Marketing`) and their epics never gate a release and stay too; bookkeeping rolls them to the next milestone at Publish (4.5), so nothing is moved by hand. An item with no PR that is not docs or marketing, including a design decision that needs an audit, goes to the next patch milestone unless the maintainer names the next minor. The first question of every release is "What is the next version?", which is also the target milestone. Check that it exists (`gh api repos/ChurchCRM/CRM/milestones`). If it does not, ask the maintainer to create it, and apply the moves once it exists | ✋ |
| 2.4 | List draft advisories (`gh api 'repos/ChurchCRM/CRM/security-advisories?state=draft'`). For each High, read the description and check the code on the release commit and on `master` for a fix that was merged without updating the advisory: a High that is still exploitable blocks. Medium and Low never block. Fix it, defer the release, or have the maintainer override it by GHSA ID. Set `patched_versions` only on a fixed advisory and clear it on any other | [Security Advisory Review](../../.agents/skills/churchcrm/security-advisory-review.md) | ✋ |

Notes:

- Run 2.1 after Phase 1, because every locale PR merge moves HEAD and invalidates the nightly SHA.
- The nightly cron is `17 3,11,19 * * *`; GitHub can start a scheduled run hours late, so check the run's start time, not the slot. A slot with no testable change since the last green run ends green without testing, so a commit that only changed ignored files still has a green nightly. A manual or release-published run always tests.
- A red nightly blocks the release until its cause is fixed.
- One red cell is triaged before it blocks: download the `cypress-junit-<cell>-<run id>` artifact and read the failure message. A harness error (for example `Cannot read properties of undefined (reading 'KeyboardEvent')`) that passes in the sibling cell is a flake: `gh run rerun <run id> --failed` re-runs only the failed jobs on the same SHA. A failure that repeats is real.
- `release-publish.yml` takes the newest successful `master` push build whose `package.json` version matches and uploads that build's ZIP. The agent gate in `release-management.md` checks HEAD = build SHA = nightly SHA.
- Master pushes use per-commit concurrency, so several builds run in parallel after a burst of merges; wait for the HEAD build. Pushes touching only ignored paths (`locale/terms/**`, `locale/messages.po`, docs, `.agents/**`, `playwright/**`, other workflow files) do not build, so the latest build can lag HEAD and its ZIP is still correct. A push that changes translations (`src/locale/**`) always builds, so the ZIP carries the newest ones.

## Phase 3: Cut the release

| # | Step | How | Status |
|---|------|-----|--------|
| 3.1 | Run **Release & Start Next** with `next_version`. It creates the draft from the tested build's ZIP, uploads the `release-notes-context-<version>` artifact, bumps `master` to `next_version` and opens the `Start <next> release` PR. It requires green build and nightly on the branch tip and refuses to replace an existing release. The marketing capture starts as its own job and does not gate the draft | [`release-publish.yml`](../../.github/workflows/release-publish.yml) → [`release-prepare.yml`](../../.github/workflows/release-prepare.yml), about 2 to 3 min | ✋ trigger / 🤖 run |
| 3.2 | Release notes: an agent reads the context artifact, writes user-facing notes following [`release-notes.md`](../../.agents/skills/churchcrm/release-notes.md) (grouped by user impact, verified language count), and after approval applies them with `gh release edit <tag> --tag <tag> --notes-file …` | Agent and skill | 🧑‍💻 |
| 3.3 | Open the `Start <next> release` PR, and do not merge it until the release's marketing capture run (`Marketing: capture screenshots & videos`, started in 3.1) has finished. Bookkeeping stops while `master` still carries the released version, so after the merge re-run `release-bookkeeping.yml` with `tag=<version>`. `pr-milestone-stamp.yml` stamps new merges into the next milestone once it is merged | PR from `build/<next>` | ✋ |
| 3.4 | Check the tag, then Publish: `gh release view <tag> --json tagName,isDraft` shows the version | GitHub release page | ✋ (approval) |

**Picking `next_version`:**
- After an `X.Y.0` release → `X.Y.1`; a minor always gets a patch cycle for feedback and bugs.
- After `X.Y.1` or later → another patch, or `X.(Y+1).0` when features are queued.

Notes:

- The workflow prints suggested patch, minor and major versions in its summary.
- Label PRs on merge so `.github/release.yml` sorts them (user-facing versus `development`/`build`/`ignore-for-release`); unlabelled PRs land under "Other Changes".
- The review and the Publish click are the release approval. Publishing starts `release-bookkeeping.yml` and the release nightly.

## Phase 4: Release goes out (automatic on Publish)

| # | What happens | Mechanism | Status |
|---|------|-----|--------|
| 4.1 | GitHub notifies everyone watching releases on `ChurchCRM/CRM` | GitHub release notifications | 🤖 |
| 4.2 | Every admin is told an upgrade is available on their next login | `AuthenticationManager::checkSystemUpdates()` → `ChurchCRMReleaseManager::checkSystemUpdateAvailable()` (admins only, stable releases only, cached per session) | 🤖 |
| 4.3 | The upgrade wizard ("What you'll gain") shows the release body from GitHub and links `changelog/<version>.md` on `master` | `ChurchCRMReleaseManager::getUpgradePreviewData()` | 🤖 |
| 4.4 | Changelog: writes `changelog/<version>.md` from the release body and adds a row to `CHANGELOG.md`, committed to `master` as `chore(changelog): sync <version> release notes` | [`release-bookkeeping.yml`](../../.github/workflows/release-bookkeeping.yml) job `sync` → `scripts/release-changelog.js` | 🤖 |
| 4.5 | Milestones: closes `<version>`, creates the next milestone (from `master`'s `package.json`) if missing, and moves every still-open issue/PR into it. Skipped for pre-releases | job `manage-milestones` | 🤖 |
| 4.6 | Docs release: sends `crm-released` to `ChurchCRM/docs.churchcrm.io`, whose workflow merges the release docs and moves the released version's open docs items to `Current` and keeps a milestone per upcoming version. Creating `release-docs/<next>` is manual (step 6.2) | job `docs-release`; needs the `DOCS_RELEASE_TOKEN` secret to send the dispatch | 🤖 |
| 4.7 | Post-release nightly: the full suite, including the upgrade matrix, runs against the published release | `build-test-nightly.yml` on `release: published` | 🤖 |
| 4.8 | Advisories: list every draft or triage advisory whose `patched_versions` is the released version or older, and every published advisory with no CVE. For each, request a CVE if it has none (`POST /repos/ChurchCRM/CRM/security-advisories/{ghsa}/cve`), then publish the draft. Report any that stay unpublished, with the reason | https://github.com/ChurchCRM/CRM/security/advisories; [Security Advisory Review](../../.agents/skills/churchcrm/security-advisory-review.md) | ✋ (same day) |
| 4.9 | Docker images: `Build & Push Docker Images` (on `release: published`) is green, and on Docker Hub the `latest`, `latest-php8-apache` and `latest-php8-fpm` tags carry the same digests as `<version>-php8-apache` and `<version>-php8-fpm`. Check: `curl -s https://hub.docker.com/v2/repositories/churchcrm/crm/tags/<tag>` and compare `digest` and `last_updated`. Only the release marked latest on GitHub moves the `latest*` tags. `docker/production/compose.yaml` tracks `latest-php8-apache`, so it needs no version bump (#10892) | job `Build & Push Docker Images` | ✋ check |
| 4.10 | Review the open PRs on the docs site: `gh pr list -R ChurchCRM/docs.churchcrm.io --state open`. The `docs: ChurchCRM <version>` PR and any held docs PR for this release (for example a docs tracking issue's PR) may still need a manual merge after the 4.6 dispatch. Merge the ones that are approved, and leave a reason on the rest. The tracking issue of each merged docs PR closes then | `docs.churchcrm.io` PR list | ✋ |

Notes:

- The in-app release notes (4.2, 4.3) are the widest-reaching copy in the release; keep them user-facing.
- `changelog/<version>.md` appears when `release-bookkeeping.yml` finishes; the wizard link resolves after that.
- Publishing with `prerelease=true` ships a release candidate without notifying admins.
- Re-run a failed bookkeeping job with `workflow_dispatch` (`tag`). The workflow rejects non-version tags and stops if `master` has not been bumped.
- The 4.4 commit touches only `.md` files, which Build/Test/Package ignores, so it starts no build.
- Merging the `Start <next> release` PR starts a normal `master` build; it creates no draft release.

## Phase 5: Open the next cycle

Step 3.3 covers it: merge the `Start <next> release` PR after the release's marketing capture has finished. Bookkeeping reads the next version from `master`, so re-run it with `tag=<version>` after that merge, and `pr-milestone-stamp.yml` then stamps new merges into the next milestone.

## Phase 6: Marketing push

Starts after Publish (3.4) and runs about a week.

| # | Step | How | Status |
|---|------|-----|--------|
| 6.1 | Check the marketing visuals are published at https://cdn.churchcrm.io | [`marketing-capture-assets.yml`](../../.github/workflows/marketing-capture-assets.yml) starts in 3.1 on `master` and never gates the release. Capture and publish are one run: English first, the other locales after it, then a last job starts `Publish visuals` in `ChurchCRM/visuals` with that run (`DOCS_RELEASE_TOKEN` needs Actions: write there). The publish step only runs on `master`. If the capture failed, fix it and re-run the workflow on `master` | 🤖 / ✋ fix and re-run |
| 6.2 | Release the docs. In `ChurchCRM/docs.churchcrm.io`: (1) set `crm-release.json` on `release-docs/<version>` to the released version and push, (2) merge the `docs: ChurchCRM <version>` pull request into `main`, (3) create `release-docs/<next version>` from `main` (command in the docs repo `DOCS_GUIDE.md`), (4) check the docs milestones: only `Current` merges into `main`; each upcoming version keeps its own version milestone, whose PRs merge only into `release-docs/<version>`. Publishing rotates the released version's open items to `Current` and closes its milestone; if one is still open, ask the maintainer where its items go | Docs PRs for the release were merged into `release-docs/<version>` as approved, and their CRM tracking issues closed then | ✋ |
| 6.3 | Launch-day announcements: Discord, "shipped, please retest" comments on fixed issues, social posts | [Release Announcement skill](../../.agents/skills/churchcrm/release-announcement.md) steps 1 and 2; [`social-media-release.md`](../../.agents/skills/churchcrm/social-media-release.md) | 🧑‍💻 |
| 6.4 | For a release with new features, write a blog post | Blog PR in `ChurchCRM.io` | ✋ |
| 6.5 | A few days later, share the blog post on social media | | ✋ |
| 6.6 | Week-long marketing push | | ✋ |
| 6.7 | Evaluate the campaign | Signals: GA4 on churchcrm.io (`utm_campaign=release-<tag>` on Discord links, `github_content` on README/CONTRIBUTING links), GitHub release ZIP download counts, PostHog `crm_version` for opted-in installs, social platform analytics | ✋ |

Marketing judgment calls stay with the maintainer: which feature leads, whether a blog is warranted, and the week's cadence. An agent can draft the week's plan (launch posts, blog draft, blog share, follow-ups) from the final release notes.

## Recovery paths

A fix to a release workflow cannot run end to end before it merges. Track it as an issue in the next milestone (labels `Integration Testing` and `build`, with a done-when checklist) and confirm it in that release.

These are what to do when a step above does not run as written. Each one was needed in the 7.8.1 release.

### The release workflow stops before it creates the draft

`release-publish.yml` stops when Build/Test/Package or the Nightly has no green run on the branch tip. A failed marketing capture does not stop it. Fix the cause, or do the two steps by hand without touching `master`:

1. Draft: download the ZIP from the green Build/Test/Package run on the release commit (`gh run download <run id> --pattern 'ChurchCRM-*'`), then `gh release create <version> ChurchCRM-<version>.zip --target <commit sha> --generate-notes --notes-start-tag <previous version> --draft`. A draft shows `untagged-<hash>` in its URL; `gh release view <version> --json tagName` still shows the version, and the git tag appears at Publish.
2. Next-version PR: `gh workflow run release-prepare.yml --ref master -f version=<next version>`.

Merge the `Start <next> release` PR only after the release's marketing capture run has finished (3.3). Bookkeeping reads the next version from `master`, and its milestone jobs stop while `master` still carries the released version, so Publish normally comes first. Merge that PR once the capture is done, then re-run `release-bookkeeping.yml` with `tag=<version>`.

### Bookkeeping failed

`gh run view <run id> --log-failed` names the job. A `403 Resource not accessible by personal access token` from `docs-release` means `DOCS_RELEASE_TOKEN` lacks Contents: write on `ChurchCRM/docs.churchcrm.io` (repository dispatch); fix the token, then re-run.

### The visuals did not reach the CDN

The capture workflow publishes on its own when it finishes green on `master`. If the capture failed, or the `Publish visuals` job failed (a missing `DOCS_RELEASE_TOKEN` fails it), publish by hand:

1. Run the capture on `master` (never on a feature branch) and wait for it to be green. Its artifact is `marketing-visuals-<run id>`, kept 30 days.
2. `gh workflow run publish.yml -R ChurchCRM/visuals -f run_id=<capture run id> -f release=<version>`. It replaces the whole published set with that run's.
3. Wait for the `pages build and deployment` run in `ChurchCRM/visuals`; the CDN lags the publish by a minute or two. Then check that the new images return 200 at `cdn.churchcrm.io/screenshots/en/desktop/<name>.png` and that `manifest.json` lists `usedIn`.
4. Re-run "Validate Docusaurus site" on the `docs: ChurchCRM <version>` pull request. It fails on every `cdn.churchcrm.io` image that returns 404, so the visuals come before the docs merge.

A capture that fails is diagnosed in `.agents/skills/churchcrm/marketing-visuals-pipeline.md` ("Fixing a failed capture").

