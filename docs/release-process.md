# ChurchCRM Release Process

The maintainer's release process, from localization sync to the marketing push, mapped step by step with timings, automation status and what is left to automate. This file is also the status page: check **Status** first.

Legend: 🤖 automated · 🧑‍💻 agent-assisted · ✋ manual · ❓ unknown

Principle: follow the automation. Do not run by hand what a workflow already runs; manual steps are only approvals and judgment calls.

---

## Status

_Last updated: 2026-09-26 05:40 UTC · `master` at `88c6ce5` · releasing **7.7.1**_

### Shipped from this work

| PR | Issue | What | State |
|---|---|---|---|
| [#10063](https://github.com/ChurchCRM/CRM/pull/10063) | [#10062](https://github.com/ChurchCRM/CRM/issues/10062) | Plurals no longer pipe-joined on upload; stuck values re-slotted on upload and download; `npm run locale:test` in CI | Merged |
| [#10065](https://github.com/ChurchCRM/CRM/pull/10065) | [#10064](https://github.com/ChurchCRM/CRM/issues/10064) | `Locale: upload missing terms` workflow; agent branches `locale/translate/**`; `docs/locale-pipeline.md`; uploader exits non-zero on failure | Merged |
| [#10067](https://github.com/ChurchCRM/CRM/pull/10067) | [#10066](https://github.com/ChurchCRM/CRM/issues/10066) | 10 untranslated plural strings reworded count-neutral; "Counts" rule in `i18n-localization.md` | Merged |

### Needs attention now

1. **The plural fix has not reached POEditor yet.** The sync run behind #10069 did execute the new uploader, but `locale-sync-poeditor.yml` Stage 3 calls it without `--yes`. The script stopped at its `[Y/n]` prompt, exited 0 without uploading, and the step printed "✅ Upload completed successfully" ([run 36220737730](https://github.com/ChurchCRM/CRM/actions/runs/36220737730)). The batch files on `master` are now correctly re-slotted (e.g. Czech 2FA: *den / dny / dní*), so:
   - **Verify:** run **Locale: upload missing terms** manually with `locales: cs` (it passes `--yes`). Expect the Czech 2FA and "They are expected…" terms to leave `locale/terms/missing/cs/` on the next sync.
   - **Fix:** add `--yes` to Stage 3 of `locale-sync-poeditor.yml` (one line).
2. **Merge [#10070](https://github.com/ChurchCRM/CRM/pull/10070)** "Update locale strings" (new terms from #10067). Then upload `messages.po` to POEditor (1.3), since that step is still manual.
3. **`Locale: upload missing terms` has never run for real.** The first `locale/translate/**` push, or the manual run above, is its first test.

### 7.7.1 release checkpoint

| Check | State | Source |
|---|---|---|
| Phase 1: no missing terms | ❌ 46 locales, 5,729 missing entries on `master` `88c6ce5`. Includes the 10 old-wording strings from #10067, which drop out once #10070 merges and `messages.po` is re-uploaded (1.3) | `locale/terms/missing/` |
| Plural terms stuck in POEditor | ⚠️ 2FA (39 locales), "Email sent to %d family." (15), "PDF successfully emailed…" (15): translated and re-slotted locally, **not yet uploaded** | see Needs attention #1 |
| Nightly on HEAD (2.1) | ❌ last green #53 on `43a3f32`; HEAD has moved. Run after Phase 1 | `build-test-nightly.yml` |
| Build/Test/Package on HEAD (2.2) | ⏳ runs in progress for the #10063/#10065/#10067 merges | `build-test-package.yml` |
| Milestone `7.7.1` (2.3) | ⚠️ 25 open issues, 0 PRs; decision pending (clear or leave for roll-forward) | Milestones |
| Stray `7.7.1` draft release (3.0) | ⚠️ exists; delete before 3.1 | Releases |
| `DOCS_RELEASE_TOKEN` secret (4.6) | ❓ unverified; the docs-milestone job fails without it | repo secrets |
| `release-bookkeeping.yml` first run (4.4–4.6) | ❓ never run under this name; watch after Publish | Actions |

### Open questions for the maintainer

- What starts the translation session at ~11:01 UTC daily (Claude Routine on another account, Copilot agent, local cron)? It must push with its own credentials for `locale-upload-missing.yml` to fire.
- Can we add a GitHub App or fine-grained token so the terms and sync PRs can auto-merge (and start the next workflow)?
- Translation trigger: Claude Code GitHub Action (needs an Anthropic API key secret) or a Claude Routine?
- Phase 6: how are social posts published (by hand per platform or a scheduler)? Where does the blog live (ChurchCRM.io?)? What is in the week-long push? How is a campaign judged?
- Roughly how long does each phase take you in practice (especially 3.2 notes and Phase 6)?
- Milestone roll-forward: keep moving unfinished items to the next milestone, or clear them?
- Remove the "Upload to GitHub Release" step from `build-test-package.yml` (source of the stray draft)?

### Next automation work, in order

1. `--yes` in `locale-sync-poeditor.yml` Stage 3 (unblocks the plural fix).
2. Upload `locale/messages.po` to POEditor on terms-PR merge, with a large-deletion guard (closes 1.3).
3. Path-allowlist check + auto-merge for terms and sync PRs (closes 1.2, 1.5; needs the App token).
4. Start the translation agent from the merged sync PR (closes the schedule question).
5. Release readiness workflow: HEAD = build SHA = nightly SHA, missing terms empty, milestone report → READY / BLOCKED.
6. Remove the draft-release upload from `build-test-package.yml`; make `release-publish.yml` refuse to replace a published release.
7. Auto-merge the `Start <next> release` PR on publish, or stop `pr-milestone-stamp.yml` stamping closed milestones.
8. One agent run for release notes (tone from last 10 releases + marketing strategy), writing into the draft.
9. Website visuals sync and docs-PR merge on publish; one-pass marketing week plan; 7- and 30-day campaign scorecard.

---

## Phase 1 — Localization sync

Goal: every feature added this cycle is localized before release, so ChurchCRM serves churches worldwide in their own language. In short: **have terms → upload → re-download → translate → upload new terms → re-download clean.**

| Beat | Steps |
|------|-------|
| Have terms | 1.1–1.2 |
| Upload to POEditor | 1.3 |
| Re-download | 1.4–1.5 |
| Translate | 1.6 |
| Upload new terms | 1.7 |
| Re-download clean | 1.8 |

Done when the final sync PR is merged and nothing is left to translate: `npm run locale:translate:list` on `master` lists no locales (`locale/terms/missing/` empty).

| # | Step | How | Status |
|---|------|-----|--------|
| 1.1 | `Locale Generate App Terms` extracts new strings on every merge to `master` touching `src/**` | [`locale-generate-terms.yml`](../.github/workflows/locale-generate-terms.yml). P50 3.5 min, **P90 4.2 min** (261 successful runs, May–Sep 2026) | 🤖 |
| 1.2 | Merge the `Update locale strings` PR it opens (branch `locale/update-<timestamp>`, label `Localization`) | Review and merge. No PR means no new strings | ✋ |
| 1.3 | Upload `locale/messages.po` from `master` to POEditor and delete terms that no longer exist | POEditor web UI: import terms, with sync / delete-obsolete-terms on | ✋ |
| 1.4 | Run `Locale Sync POEditor` manually so the latest translations land before the release; it opens a `locale: update translations from POEditor - <date>` PR (e.g. [#10061](https://github.com/ChurchCRM/CRM/pull/10061)) | [`locale-sync-poeditor.yml`](../.github/workflows/locale-sync-poeditor.yml), `workflow_dispatch`. Manual runs: P50 7.3 min, **P90 8.1 min** (31 runs); scheduled: P90 7.7 min | ✋ trigger / 🤖 run |
| 1.5 | Quick scan of the POEditor PR for bad files, then merge. Usually trusted as-is | Skim the file list | ✋ |
| 1.6 | Translate every missing term in `locale/terms/missing/<locale>/` with Claude, using ChurchCRM/church-specific vocabulary | [`/locale-translate --all`](../.claude/commands/locale-translate.md) (via [`/locale-release`](../.claude/commands/locale-release.md)). Fresh `locale/translate/<version>-<date>-<time>` branch (was `locales/…` before #10065), one commit and push per locale. Measured from branch creation to last commit (7 runs, Aug–Sep 2026): 9 min to 3 h 13 min, median 37 min; scales with terms per locale (~10 → ~25 min, ~30 → ~40 min, ~155 → ~3 h) | 🧑‍💻 |
| 1.7 | Upload the translated terms to POEditor | **Since #10065:** every push to `locale/translate/**` runs [`locale-upload-missing.yml`](../.github/workflows/locale-upload-missing.yml), which uploads the changed locales. Fallback: run that workflow manually (`locales` input), or `node locale/scripts/poeditor-upload-missing.js -y` locally with `POEDITOR_TOKEN`. ~22 s between locales | 🤖 (was ✋) |
| 1.8 | `Locale Sync POEditor` runs again to download the new translations; scan and merge its PR (same as 1.4 → 1.5) | **Since #10065** the upload workflow dispatches [`locale-sync-poeditor.yml`](../.github/workflows/locale-sync-poeditor.yml) itself. **P90 8.1 min** | 🤖 trigger / 🤖 run / ✋ merge |

**Phase 1 wall-clock (typical patch):** ~4 + ~8 + ~37 + ~17 + ~8 min ≈ **75 min of machine time**, plus each wait for a person to trigger, scan or merge (4 hand-offs: 1.2/1.3, 1.4, 1.5, 1.7/1.8). A feature-heavy minor release adds ~3 h in 1.6.

### Automation notes

- 1.1: nothing to do. Of 344 runs, 20% were cancelled (a newer `src/` merge superseded them) and 4% failed (none since 2026-07-24). If a release gate wants proof, it can check that no run is in progress and the latest run succeeded on or after the last `src/` commit.
- 1.2: the PR only touches `locale/messages.po` and `src/locale/i18n/`, so it could auto-merge once required checks pass.
- 1.3: not scripted today. Existing scripts only download translations (`poeditor-downloader.js`) or upload translations for missing terms (`poeditor-upload-missing.js`). Candidate: a workflow on push to `master` touching `locale/messages.po` (i.e. when the 1.2 PR merges) that calls POEditor `POST /v2/projects/upload` with `updating=terms` and `sync_terms=1`. It would reuse the `POEDITOR_TOKEN` secret, which must be able to edit terms. Guard: `sync_terms` deletes each removed term *and its translations*, so first diff against `/v2/terms/list` and fail when deletions exceed a threshold. This protects against a broken extraction wiping the project.
- 1.4: the same workflow already runs daily at 00:00 UTC. The manual run exists only to pick up translations made after the 1.3 upload. If 1.3 is automated, it can dispatch this workflow when it finishes, so the manual trigger goes away.
- 1.5: "no bad file" can be a check. The sync PR should only touch `locale/`, `locale/terms/missing/<locale>/`, `src/locale/`, `src/locale/i18n/*.json` and `src/locale/textdomain/<locale>/LC_MESSAGES/*` (#10061 fits). A job can fail on any path outside that set, invalid JSON, a `.po` that `msgfmt --check` rejects, or a locale file that shrinks sharply. With that check green, the PR can auto-merge, and a person only looks when the check fails.
- 1.6: already agent-driven. All 7 branches were cut at ~11:01 UTC, so the session is started on a fixed schedule; no upload-refresh commits appear on them, so the per-locale upload the command asks for is not happening in-session; 1.7 does it instead. The remaining manual part is starting the session and watching it. Candidate: when the 1.5 PR merges with a non-empty `locale/terms/missing/`, start the translation automatically (a scheduled Claude Routine, or a GitHub Action running Claude Code with the `locale-translate` command). The per-locale commit/push/upload rule already makes it safe to interrupt.
- 1.7: done in #10065. The translation agent must push with its own credentials; pushes made with the Actions `GITHUB_TOKEN` start no workflows. The workflow has not run for real yet (see Status).
- 1.8: dispatch done in #10065. Still manual: merging the sync PR (auto-merge behind the 1.5 check is planned).
- Plurals: the uploader used to pipe-join plural forms, which POEditor stored whole in the first slot (stuck) or dropped (`{{count}}` pairs). Fixed in #10063 (#10062); 10 untranslated plural strings were reworded count-neutral in #10067 (#10066). See Appendix A §7.

## Phase 2 — Built and tested

Goal: the exact commit being released is built, fully tested, and its scope is clean.

**Exit checkpoint — "software is built and tested":**
- [ ] Phase 1 done (no missing terms), so HEAD will not move again for locale PRs
- [ ] Latest green nightly SHA = `master` HEAD (2.1)
- [ ] Build/Test/Package green on `master` HEAD, ZIP artifact present (2.2)
- [ ] Milestone `<version>` has no open issue or PR that should ship (2.3)

**Wall-clock:** 2.1 and 2.2 run in parallel, so about **48 min P90** when the nightly must be run by hand, plus the milestone review.

| # | Step | How | Status |
|---|------|-----|--------|
| 2.1 | Compare the latest successful nightly run's SHA with `master` HEAD. If they differ, run the nightly manually and wait for green | [`build-test-nightly.yml`](../.github/workflows/build-test-nightly.yml): build, root and subdir suites, setup wizard, locale smoke, upgrade matrix (PHP 8.4/8.5 × MySQL/MariaDB). Manual runs: P50 29 min, **P90 47.5 min** (8 runs); scheduled: P50 21 min, P90 23 min (29 runs); Aug 17 – Sep 25 2026 | ✋ check & trigger / 🤖 run |
| 2.2 | Confirm `Build, Test and Package` on `master` HEAD is completed and green. Its `ChurchCRM-<version>.zip` artifact is the file that gets released | [`build-test-package.yml`](../.github/workflows/build-test-package.yml) runs on every push to `master`. Master pushes: P50 26 min, **P90 36 min** (30 successful runs, Sep 23–26 2026) | 🤖 run / ✋ check |
| 2.3 | Review the GitHub milestone matching `package.json` version on `master`; take off any open issue or PR (it will not ship). Double-check of automation | Milestones → `<version>`. Anything blocking gets fixed first; the rest is removed | ✋ (judgment) |

### Automation notes

- 2.1: run after Phase 1 finishes, because every locale PR merge moves HEAD and invalidates the nightly SHA. The SHA comparison is scriptable. A `release-readiness` workflow could compare HEAD against the last green nightly and dispatch the nightly itself when they differ.
- The cron is `0 3 * * *` but scheduled runs start at ~08:00–08:30 UTC (GitHub queue delay). Don't count on the 03:00 run being done by morning in US time zones.
- Reliability: 10 of the last 53 runs failed (8 of the last 30), most recently #48 on 2026-09-22. A red nightly blocks the release until it's root-caused.
- Check, 2026-09-26: last green nightly #53 is on `43a3f32`, `master` HEAD is `f87e3b9` (#10061 merged). They differ (12 commits: locale files, marketing pipeline, CI runner pin, one Cypress fix), so 2.1 requires a run.
- 2.2: `release-publish.yml` already enforces most of this. It takes the newest successful `master` push build whose `package.json` version matches, and uploads that build's ZIP. What it does *not* check is that the build is on HEAD or that the nightly passed on the same SHA; that is `release-management.md` §3 (HEAD = build SHA = nightly SHA). A readiness workflow can check all three together.
- 2.2: master pushes use per-commit concurrency (not cancelled), so several builds run in parallel after a burst of merges. Wait for the HEAD one specifically. Pushes touching only ignored paths (`locale/terms/**`, `locale/messages.po`, docs, `.agents/**`) don't build, so the latest build can lag HEAD without anything being wrong. The ZIP is still correct for release.
- Check, 2026-09-26 00:10 UTC: HEAD `f87e3b9` (#10061) build #8272 in progress; `ffe1374` build #8270 in progress.
- 2.3: already backed by automation. [`pr-milestone-stamp.yml`](../.github/workflows/pr-milestone-stamp.yml) stamps merged PRs and their issues, and `release-bookkeeping.yml` rolls every open item to the next milestone and closes this one on publish. The manual pass is only to catch a *blocker* hiding in the list. Keep it as a report, not a task: a readiness job can list open items and flag `bug`/`P0`/`Security` ones for a decision.
- Check, 2026-09-26: milestone `7.7.1` has **25 open issues, 0 open PRs**. Almost all were last touched 2026-09-24 (rolled forward from 7.7.0), and some date back to 2017 (#3639). The milestone is doubling as a backlog that moves forward every release. That is why the manual review feels necessary. Either stop rolling unscheduled items forward (bookkeeping could clear the milestone instead of moving it), or accept the carry-over and only review flagged items.

## Phase 3 — Cut the release

| # | Step | How | Status |
|---|------|-----|--------|
| 3.0 | Delete any existing `<version>` draft release: its contents are of unknown provenance. Let 3.1 create a fresh one with the verified ZIP | Releases → draft → Delete | ✋ |
| 3.1 | Run **Release & Start Next** with `next_version`. It creates the draft GitHub release from the tested build's ZIP, bumps `master` to `next_version`, and opens the `Start <next> release` PR | [`release-publish.yml`](../.github/workflows/release-publish.yml) → [`release-prepare.yml`](../.github/workflows/release-prepare.yml). Defaults: `draft=true`, other inputs blank. Runs ~2–3 min (15 successes; the 7 failures all stopped in <20 s at the version guard) | ✋ trigger (judgment) / 🤖 run |
| 3.2 | Turn the generated notes into user-facing release notes: (a) agent does a deeper review of what actually changed; (b) keep only what users and admins see; drop testing, security hardening, CI and refactors to a calm one-liner; (c) feed the raw result to Gemini with the template and tone of the last 10 releases; (d) check it fits the marketing strategy | Agent + [`release-notes.md`](../.agents/skills/churchcrm/release-notes.md), then Gemini. Draft-to-publish gap, which bounds 3.2–3.3: 7.6.3 2 min, 7.6.4 3 min, 7.6.2 12 min, 7.6.1 43 min, 7.7.0 55 min | 🧑‍💻 (two tools) |
| 3.3 | Review the formatted notes, make small edits, paste them into the draft release, and **Publish** | GitHub release page | ✋ (approval) |

**Picking `next_version`:**
- After an `X.Y.0` release → `X.Y.1`. A minor always gets a patch cycle for feedback and bugs.
- After `X.Y.1` or later → judgment: another patch, or `X.(Y+1).0` if features are queued.
- History: 7.6.0 → 7.6.1 (Aug 17) → 7.6.2 (Aug 25) → 7.6.3 (Aug 31) → 7.6.4 (Sep 1) → 7.7.0 (Sep 18) → 7.7.1.

### Automation notes

- 3.1: the run itself is automated. The human input is `next_version`. The rule above can pre-fill a suggestion (the workflow already prints suggested patch/minor/major in its summary). A readiness report could propose "patch unless `enhancement`-labelled PRs are waiting on `master`" and leave the call to the maintainer.
- 3.1: a `7.7.1` draft already exists (created outside this workflow's run history). The workflow deletes and recreates any release/tag with the same version. That is fine for a draft but destructive for a published release, and the workflow does not stop you. Add a guard that refuses when the existing release is not a draft.
- 3.0: the stray draft comes from `build-test-package.yml` itself. Its `package` job, on every push to `master`, creates a `<version>` draft (`--generate-notes --draft`) if none exists, then `gh release upload --clobber`s that build's ZIP. So the draft always holds the ZIP of whichever master build *finished* last, which is not necessarily HEAD and not necessarily nightly-verified. That's the "unknown status". `release-publish.yml` then deletes and recreates it anyway. Fix: remove the "Upload to GitHub Release" step from `build-test-package.yml` (the ZIP is still kept as a 90-day artifact, which is what `release-publish.yml` downloads). 3.0 then disappears.
- 3.2: two models and a copy-paste hand-off (agent → Gemini). `release-notes.md` already defines the 🌸 template, the user-first rules and the fact-check. Adding "read the last 10 published release bodies for tone" and a link to the marketing strategy would let one agent run do (a)–(d) and write the result straight into the draft body for review. It can start automatically when 3.1 creates the draft.
- 3.2: the raw input is weak. In the 7.7.1 generated notes nearly every PR fell under "Other Changes" because PRs lack the labels [`.github/release.yml`](../.github/release.yml) sorts on. Labelling PRs on merge (user-facing vs. `development`/`build`/`ignore-for-release`) would pre-filter the developer noise before any model sees it.
- 3.2: the marketing strategy isn't in this repo. Link its source (e.g. the `ChurchCRM/marketing` repo) from `release-notes.md` so the check in (d) is repeatable.
- 3.2: the last 5 releases all say "45+ supported languages", a hard-coded count that `release-notes.md` says to verify. Worth computing from `src/locale/` instead.
- 3.3: the review and the Publish click are the release approval, so they stay manual by design. The paste goes away if 3.2 writes into the draft body directly. Publishing fires `release-bookkeeping.yml` (changelog file, milestone close/roll-forward, docs milestones) and the release-triggered nightly automatically.

## Phase 4 — Release goes out (automatic on Publish)

| # | What happens | Mechanism | Status |
|---|------|-----|--------|
| 4.1 | GitHub notifies everyone watching releases on `ChurchCRM/CRM` | GitHub release notifications | 🤖 |
| 4.2 | Every ChurchCRM admin is told an upgrade is available on their next login | `AuthenticationManager::checkSystemUpdates()` → `ChurchCRMReleaseManager::checkSystemUpdateAvailable()` (admins only, stable releases only, cached per session) → notification on page load | 🤖 |
| 4.3 | The upgrade wizard ("What you'll gain") shows the release body from GitHub and links `changelog/<version>.md` on `master` | `ChurchCRMReleaseManager::getUpgradePreviewData()`; the changelog file is written by `release-bookkeeping.yml` | 🤖 |
| 4.4 | Changelog: writes `changelog/<version>.md` from the release body and adds a row to `CHANGELOG.md`, committed straight to `master` as `chore(changelog): sync <version> release notes` | [`release-bookkeeping.yml`](../.github/workflows/release-bookkeeping.yml) job `sync` → `scripts/release-changelog.js` (no model call; the one-line highlight comes from the body's `## ` headings) | 🤖 |
| 4.5 | Milestones: closes `<version>`, creates the next milestone (from `master`'s `package.json`) if missing, and moves every still-open issue/PR into it. Skipped for pre-releases | same workflow, job `manage-milestones` | 🤖 |
| 4.6 | Docs milestones: makes sure `<version>` and next exist in `ChurchCRM/docs.churchcrm.io`, keeping held docs PRs on the released one | same workflow, job `sync-docs-milestones`; needs the `DOCS_RELEASE_TOKEN` secret | 🤖 |
| 4.7 | Post-release nightly: the full nightly suite, including the upgrade matrix, runs against the published release | [`build-test-nightly.yml`](../.github/workflows/build-test-nightly.yml) on `release: published` | 🤖 |

### Automation notes

- 4.2/4.3: the release notes from 3.2 are what every admin reads in-app, so they are the widest-reaching copy in the release, more than social posts. That's one more reason to keep them user-facing.
- 4.3: the changelog link is dead until `release-bookkeeping.yml` finishes and pushes `changelog/<version>.md` to `master`. If that job fails, the wizard links a 404. Add a post-publish check that the file exists.
- 4.2: pre-releases are skipped, so `prerelease=true` in 3.1 is a safe way to ship an RC without notifying admins.
- 4.4–4.6 have **never run under this name**. `sync-changelog.yml` became `release-bookkeeping.yml` on 2026-09-20 (#9960), a day after 7.7.0 shipped. The docs-milestone job was added 2026-09-23 (#10004). The 7.7.1 publish is their first real run, so watch the Actions tab afterwards, and confirm `DOCS_RELEASE_TOKEN` is set, or 4.6 fails. Both can be re-run by hand (`workflow_dispatch`, input `tag`).
- 4.4 pushes a `.md`-only commit to `master`, and `**/*.md` is path-ignored by Build/Test/Package, so it doesn't start a new build.
- Later, merging the `Start <next> release` PR from 3.1 *does* build, and that build's `package` job creates the next version's draft release. That's the stray draft deleted in 3.0.
- #9960 also deleted `scripts/release-marketing.js`, which used to generate marketing copy on release. Marketing is now a separate manual step (Phase 6).

## Phase 5 — Open the next cycle

| # | Step | How | Status |
|---|------|-----|--------|
| 5.1 | Merge the `Start <next> release` PR (opened by 3.1) so `master` is ready for new PR merges | PR from `build/<next>`, label `Build`: bumps `package.json`, `composer.json`, `upgrade.json`, demo `seed.sql` | ✋ |

### Automation notes

- 5.1 matters because [`pr-milestone-stamp.yml`](../.github/workflows/pr-milestone-stamp.yml) stamps every merged PR with the milestone named after `master`'s `package.json` version, and it falls back to *closed* milestones. Any PR merged between Publish (3.3) and 5.1 is stamped into the release that already shipped. That makes 5.1 time-critical, and it's why it sits apart from the marketing work.
- Candidate: when the release is published, auto-merge the `Start <next> release` PR if its checks are green (e.g. a job in `release-bookkeeping.yml`, or enable auto-merge in `release-prepare.yml` and let publish approve it). Until then, a guard in `pr-milestone-stamp.yml` could refuse to stamp into a closed milestone and warn instead.
- Merging 5.1 starts a `master` build whose `package` job creates the next version's draft release: the stray draft deleted in 3.0 next time.

## Phase 6 — Marketing push

Starts after 5.1. Runs over about a week. Order as practised:

| # | Step | How | Status |
|---|------|-----|--------|
| 6.1 | Sync marketing visuals (screenshots/videos) to the website via a PR on `ChurchCRM/ChurchCRM.io`, and merge it | `npm run publish:visuals` ([`scripts/publish-marketing-visuals.sh`](../scripts/publish-marketing-visuals.sh)) copies `playwright/artifacts/` into a sibling `ChurchCRM.io` checkout; commit + PR by hand. Visuals kept current by [`marketing-capture-assets.yml`](../.github/workflows/marketing-capture-assets.yml) | ✋ (local script + PR) |
| 6.2 | Once 6.1 is merged, merge the docs PRs held open for this release in `ChurchCRM/docs.churchcrm.io` (milestone `<version>` or label `docs-pending-release`) | `/release-announcement` step 3 lists them; maintainer merges | 🧑‍💻 list / ✋ merge |
| 6.3 | Launch-day announcements: Discord, "shipped, please retest" comments on fixed issues, social posts | [`/release-announcement`](../.claude/commands/release-announcement.md) steps 1–2; [`social-media-release.md`](../.agents/skills/churchcrm/social-media-release.md). How posts are published: TBD | 🧑‍💻 / ❓ |
| 6.4 | If the release has new features, write a blog post for them | Blog PR (repo TBD, likely `ChurchCRM.io`) | ✋ (judgment) |
| 6.5 | A few days later, share the blog post on social media | TBD | ✋ |
| 6.6 | Week-long marketing push | TBD | ✋ |
| 6.7 | Evaluate the campaign: how the release and its marketing performed | Signals available: GA4 on churchcrm.io (UTM `utm_campaign=release-<tag>` on Discord links; `github_content` on README/CONTRIBUTING links); GitHub release ZIP download counts; PostHog telemetry carries `crm_version`, so adoption of the new version among opted-in installs is visible; social platform analytics. Method: TBD | ✋ |

### Automation notes

- 6.1: runs from a local checkout of both repos. As a workflow on `release: published` (or on the marketing-visuals PR merging), it could check out `ChurchCRM.io`, run the same copy, and open the PR. That needs a token with write access to that repo, like `DOCS_RELEASE_TOKEN` for docs.
- 6.2: docs PRs are already tagged to the release by milestone. After Publish they could be marked ready or auto-merged, once the website sync lands if order matters.
- 6.3–6.6: `scripts/release-marketing.js` used to generate copy on release; it was removed in #9960 in favour of the skills. What stays manual is judgment: which feature to lead with, whether a blog is warranted, and the week's cadence. An agent can draft the whole week's plan (launch posts, blog draft, day-3 blog share, follow-ups) from the final release notes in one pass. The maintainer approves, and a scheduler posts it.
- 6.7: most of the data already exists in four separate places. A scheduled agent run about 7 and 30 days after Publish could pull GA4 sessions by `utm_campaign`, ZIP `download_count` from the GitHub API, and PostHog installs by `crm_version`. It compares them with the previous release and appends a short scorecard to `changelog/<version>.md` or a tracking issue. Gaps: only Discord links carry a release-specific UTM tag, so social and blog links need `utm_campaign=release-<tag>` too. Telemetry defaults to `none`, so adoption counts cover only opted-in installs.

## Cleanup inventory

Earlier attempts to document and automate the release left overlapping and stale pieces. Goal: this file is the one runbook; each skill covers one step and links back here.

### Docs and skills

| File | Problem | Proposed action |
|------|---------|-----------------|
| `.agents/skills/churchcrm/release-management.md` | Good gate logic (SHA = build = nightly, approvals), but not linked to Phase 1 (locale) or the actual 3.0 / 3.2 / 5.1 practice. Not listed in the `SKILL.md` index | Keep. Trim to the agent's gate/report job and link here |
| `release-notes.md` | Real process uses Gemini + last-10-releases tone + marketing strategy; skill has none of that. Says don't hard-code locale count; every release does | Add tone sampling + strategy link; make it the single notes tool (drop Gemini hop) |
| `release-announcement.md` + `.claude/commands/release-announcement.md` | Refers to a "community agent" as the public voice; unclear if it still exists | Confirm; remove the reference if gone |
| `social-media-release.md` | Says run after "changelog file is written (see release-notes.md)", which no longer happens there. Writes `social-media/<version>/`, which has never existed | Rewrite to match real 5.4 practice |
| `locale-translation-workflow.md` (556 lines) + `.claude/commands/locale-release.md` + `locale-translate.md` + `locale-translate-agent-prompt.md` | Four overlapping locale docs. Per-locale upload rule wasn't followed (1.6/1.7). None described the 6-beat loop | **Partly done (#10063, #10065):** pipe-format guidance removed, "the push uploads it", dead links fixed, loop documented in `docs/locale-pipeline.md`. Still to do: collapse the four into one |
| `marketing-visuals-pipeline.md` | Fine as a tool skill; has `<!-- learned: -->` essays that CLAUDE.md says not to add | Leave; tidy later |
| `scripts/README.md` | Describes `startNewRelease.js` as "used by maintainers", but it's only called by `release-prepare.yml` | One-line fix |
| `.cursor/rules/`, `.github/copilot-instructions.md`, `.github/skills/` | No release content, so nothing conflicts | None |

### Workflows

| File | Problem | Proposed action |
|------|---------|-----------------|
| `build-test-package.yml` `package` job | Creates/overwrites a `<version>` draft release on every `master` push (source of 3.0) | Remove the "Upload to GitHub Release" step |
| `release-publish.yml` | Deletes and recreates an existing release/tag even if published; header points to a "Start New Release" workflow now named "Release: start next version" | Refuse when the existing release isn't a draft; fix comment |
| `release-bookkeeping.yml` | Never run under this name; docs job needs `DOCS_RELEASE_TOKEN`. Rolls every open item forward, turning milestones into a growing backlog (2.3) | Watch the 7.7.1 run; decide roll-forward vs. clear |
| `pr-milestone-stamp.yml` | Stamps into closed milestones between Publish and 5.1 | Skip closed milestones, or auto-merge the Start PR on publish (5.1) |
| `locale-generate-terms.yml`, `locale-sync-poeditor.yml` | Work; the gaps are the missing links between them (1.3, 1.7) | 1.7 done (#10065). 1.3 (upload `messages.po`) still open |
| `locale-sync-poeditor.yml` Stage 3 | Runs `poeditor-upload-missing.js` **without `--yes`**: the script stops at its `[Y/n]` prompt, exits 0 without uploading, and the step prints "✅ Upload completed successfully" (seen in run 36220737730) | Add `--yes` |
| `locale-generate-terms.yml` | Copies the whole triggering commit message into the terms PR body (see #10070) | Keep only SHA + subject |

### Git hygiene

- 7 old `locales/*` translation branches on the remote (Aug 17 – Sep 16). `locales/7.7.0-2026-09-16-110114` holds `{{count}}` translations that never reached POEditor; those strings were reworded in #10067, so the branches can go (`/repo-health` covers stale branches).

---

## Appendix A: Locale automation design

Status: proposal; §7 shipped in #10063, the upload workflow and `locale/translate/**` branches shipped in #10065 (`docs/locale-pipeline.md` is the operator doc). Implements Phase 1 of `release-process.md` (have terms → upload → re-download → translate → upload new terms → re-download clean) with no manual clicks, until every locale is fully translated.

### 1. What existed before #10063 / #10065

| Branch pattern | Created by | Carries | Ends as |
|---|---|---|---|
| `locale/update-<timestamp>` | `locale-generate-terms.yml` | `locale/messages.po`, `src/locale/i18n/` (new source strings) | PR → merged by hand |
| `locale/<version>` | `locale-sync-poeditor.yml` (`peter-evans/create-pull-request`) | translations from POEditor, `locale/terms/missing/**` | PR → merged by hand |
| `locales/<version>-<date>-<time>` | `/locale-translate` session (`locale-branch-manager.js`) | translated `locale/terms/missing/**` | never merged; uploaded from the maintainer's machine; 7 left on the remote |

Problems:

1. **Three prefixes, none of which say what the branch is for.** `locale/` vs `locales/` differ by one letter. `locale-branch-manager.js`'s own header still documents `locale/{VERSION}-{DATE}`.
2. **The chain is broken in two places.** Nothing uploads `messages.po` to POEditor (1.3). The translated batches never reach CI, so the upload happens locally (1.7).
3. **The sync workflow already uploads.** Stage 3 runs `poeditor-upload-missing.js` before downloading, but on `master`, where the batch files hold untranslated stubs, so it uploads nothing. The translations sit on a `locales/*` branch it never sees.
4. **Bot-made events don't chain.** Pushes and PRs created with `GITHUB_TOKEN` do not start other workflows (except `workflow_dispatch`/`repository_dispatch`). Merging a bot PR by hand is what "wakes" the next step today, so every hand-off needs a person.
5. **No definition of done.** Nothing checks that every locale reached zero missing terms before a release. On `master` today: 46 locales, 5,256 missing entries.

### 2. Branch namespace: the branch name is the trigger

One prefix, `locale/`, and a second segment naming the stage. Each stage has exactly one workflow and one exit.

```
locale/terms/<version>             generation: source strings extracted from src/
locale/sync/<version>              download:   POEditor → repo (translations + missing batches)
locale/translate/<version>-<ts>    translation: agent session output, uploaded to POEditor
```

| Branch | Opened by | Trigger it drives | Merged? |
|---|---|---|---|
| `locale/terms/<version>` | Terms workflow, on `src/**` push to `master` (reused and force-updated like the sync branch; one open PR at a time) | Checks: path allowlist (`locale/messages.po`, `src/locale/i18n/**`). Auto-merge when green | Yes |
| `locale/sync/<version>` | Sync workflow | Checks: path allowlist + JSON/`msgfmt` validity + shrink guard (1.5). Auto-merge when green | Yes |
| `locale/translate/<version>-<ts>` | Translation agent | `push` → upload that session's changed locales to POEditor | **No.** The upload *is* the delivery. Branch deleted after a successful upload |

Why translate branches are never merged: `locale/terms/missing/**` is a generated report of what POEditor lacks. Merging hand-edited copies into `master` fights the next sync, which rewrites them. POEditor is the source of truth for translations; the repo only receives them through `locale/sync`.

### 3. Workflow chain

```
merge to master touching src/**
   │
   ▼
[terms]  locale-generate-terms.yml ──PR── locale/terms/<v> ──auto-merge──┐
                                                                         ▼
[upload-terms]  NEW locale-upload-terms.yml  (push to master: locale/messages.po)
   POEditor /projects/upload  updating=terms  sync_terms=1
   guard: diff vs /terms/list, fail if deletions > threshold
   then: gh workflow run locale-sync-poeditor.yml
                                                                         │
                                                                         ▼
[sync]  locale-sync-poeditor.yml ──PR── locale/sync/<v> ──auto-merge─────┐
                                                                         ▼
[translate]  NEW trigger: missing terms remain on master?
   yes → start translation agent (all locales, §4) on locale/translate/<v>-<ts>
   no  → Phase 1 done ✅
                                                                         │
                                                                         ▼
[upload-translations]  NEW locale-upload-translations.yml
   on: push to locale/translate/**
   uploads only the locales changed in the push (poeditor-upload-missing.js --locale <changed> --yes)
   when the agent pushes its final commit (marker file or empty-diff "done" commit):
   gh workflow run locale-sync-poeditor.yml   → loops back to [sync]
```

Loop exit: a sync PR whose missing-terms set is empty for every locale (§4). The second sync is the "re-download clean".

#### Connecting the workflows

| Link | Mechanism | Why |
|---|---|---|
| terms PR → merge | Auto-merge enabled at PR creation, by a **GitHub App token** | A PR merged with `GITHUB_TOKEN` fires no `push`, so `upload-terms` would never start |
| merge → upload-terms | `push` on `master`, `paths: locale/messages.po` | Works once the merge is made by the App |
| upload-terms → sync | `gh workflow run` (`workflow_dispatch`) | Dispatch is allowed from `GITHUB_TOKEN` |
| sync PR → merge → translate | Auto-merge (App token), then `push` on `master`, `paths: locale/terms/missing/**` | Same rule as above |
| translate → start agent | Claude Code GitHub Action, or fire a Claude Routine | Replaces the unknown 11:01 UTC schedule with an event |
| agent push → upload | `push` on `locale/translate/**` | The agent pushes with its own credentials, so the event fires |
| upload → sync | `gh workflow run` | Dispatch |

One new secret: a GitHub App (or fine-grained PAT) with `contents` + `pull-requests` write, used only for opening and auto-merging the `locale/terms` and `locale/sync` PRs. `POEDITOR_TOKEN` already exists and must be allowed to edit terms (for `sync_terms`).

Concurrency: one group, `locale-pipeline`, across all four workflows with `cancel-in-progress: false`, so a new `src/` merge queues behind an in-flight translate loop instead of cancelling it half-way.

### 4. Coverage rule: every locale, no tiers

The CRM's localization goal is independent of the marketing site. ChurchCRM serves churches worldwide, so **every locale in `src/locale/locales.json` must be fully translated for every release.** The website's 8 languages and the marketing evidence markets do not rank or exclude CRM locales.

- Missing terms stay where they are: `locale/terms/missing/<locale>/<locale>-<batch>.json`, one folder per POEditor locale, flat.
- The translation agent processes every folder. Order only matters for resuming after a timeout (the per-locale commit/push makes any order safe).
- English variants (`en`, `en-au`, `en-ca`, …) are included. Most of their terms are identical to the source, so they resolve through `english-ok.json` rather than real translation. They still count toward "all translated".
- Marketing's locale choices stay in the marketing repo (website languages, screenshot locales in `capture-all-locales.sh`). They consume the CRM's translations; they do not steer them.

### 5. Release gate (feeds Phase 2)

Phase 1 is done for a release when:

- `locale/terms/missing/` is empty on `master` for **every** locale (blocking)
- no `locale/*` PR is open and no locale workflow is running
- no `locale/translate/*` branch is left (each is deleted after its upload)

### 6. Known issues to fix along the way

- Plural terms can't round-trip. See §7; this is the blocker for an all-locales gate.
- `/locale-translate`, `locale-release.md` and `locale-translate-agent-prompt.md` all cite `.agents/skills/churchcrm/locale-ai-translation.md` as the "authoritative" church-vocabulary reference. **That file does not exist** (it was deprecated). The translation agent is working without its vocabulary guide.
- `locale-branch-manager.js` documents `locale/{VERSION}-{DATE}` but creates `locales/…`. Replace it with the §2 names.
- `locale-sync-poeditor.yml` runs `poeditor-upload-missing.js` without `--yes`. Today it never reaches the prompt, because `master` has nothing translated to upload. Once there is something to upload, it stops at an interactive `[Y/n]` prompt with no terminal. Pass `--yes` explicitly.

### 7. Plural round-trip (the one/few/many problem)

Two kinds of plural, both broken by the uploader's pipe-joining (`convertPluralsToSeparated`):

| Kind | In `messages.po` | In POEditor | What the old uploader sent | Result |
|---|---|---|---|---|
| **gettext plural** (17 terms, PHP `%d`) | `msgid` + `msgid_plural` | one term, one slot per form of the language (ru 4, cs 3, es-CO 3, ar 6, id 1) | `"A\|B\|C\|D"` | stored whole in the first slot, so the term stays missing forever and the uploader skips it as "incomplete". **Stuck:** 4 terms in up to 36 locales |
| **context pair** (9 terms, i18next `{{count}}`) | two entries, `msgctxt "one"` / `msgctxt "other"` | two separate terms | `"A\|B"` against the context-less key | **silently dropped**. The Sep 16 run's translations for all 9 terms in 42 locales never arrived |

The downloader was already right: the batch files carry POEditor's own slot names for each language.

**Fix (merged, #10063):** `locale/scripts/lib/poeditor-plurals.js`
- `loadSourceTermKinds(messages.po)` classifies each term.
- `buildPoeditorPayload` sends gettext plurals as `{ term: { one, few, … } }` and context pairs as `{ one: { term }, other: { term } }`. Nothing is pipe-joined.
- `repairJoinedPlural` re-slots a stuck first-slot value when the part count matches, and otherwise blanks it for re-translation. It runs on upload and on download, so the current stuck batch files upload correctly on the next run.
- `npm run locale:test` (node:test, 9 cases incl. 2/3/4/6-form languages and the real `messages.po`), run in `code-quality.yml`.
- `--dry-run` writes each locale's payload to `locale/.work/upload/<code>.json` for inspection.

**Still to confirm against the live API:** that POEditor's `key_value_json` import accepts both nested shapes (they mirror its export). Not yet exercised: see Status.

**Follow-ups, not in this fix:**
- Recover the Sep 16 context-pair translations from `locales/7.7.0-2026-09-16-110114` instead of re-translating them.
- i18next `{{count}}` terms only define `one`/`other` contexts, so ru/uk/pl/cs/ar users get the `other` form where their grammar needs `few`/`many`. That's a source-extraction limitation to raise separately.

### 8. Rollout, smallest risk first

1. **Names:** rename branch prefixes (terms, sync, branch-manager). No behaviour change.
2. **Plurals (§7):** ✅ merged (#10063). Still to do: confirm against the live API; restore the missing church-vocabulary skill.
3. **Checks and auto-merge:** path-allowlist check job for `locale/terms` and `locale/sync` PRs; GitHub App token; enable auto-merge.
4. **upload-terms workflow** with the deletion guard (closes 1.3).
5. **upload-translations workflow** on `locale/translate/**` (closes 1.7). ✅ merged (#10065) as `locale-upload-missing.yml`.
6. **Event-started translation agent;** retire the 11:01 UTC schedule.
7. **All-locales release gate** in the readiness check (after step 2).

Each step is its own PR and issue, and each leaves the pipeline working if the next never lands.
