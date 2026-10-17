# ChurchCRM Release Process

The maintainer runbook from localization sync to the marketing push. Follow the automation: workflows run what they own, and manual steps are approvals and judgment calls.

Legend: 🤖 automated · 🧑‍💻 agent-assisted · ✋ manual

## Checklist

1. **Locales clean:** `locale/terms/missing/` is empty on `master` (Phase 1). New strings follow the count-neutral rule in `i18n-localization.md`.
2. **Built and tested:** Build/Test/Package and the nightly are green on the same `master` HEAD, and the milestone for the `package.json` version is reviewed (Phase 2).
3. **Release & Start Next** with `next_version`, the only input (3.1). It requires green Build/Test/Package, Nightly and marketing capture on the branch tip, and refuses to replace an existing release.
4. **Notes:** ask an agent to "write the `<version>` release notes". It reads the `release-notes-context-<version>` artifact and applies the approved text with `gh release edit <version> --tag <version> --notes-file …` (3.2).
5. **Merge the `Start <next> release` PR before publishing** (3.3).
6. **Check the tag, then Publish** (3.4): `gh release view <version> -R ChurchCRM/CRM --json tagName,isDraft` shows `"tagName":"<version>"`.
7. **After Publish** (Phase 4): bookkeeping `sync`, `manage-milestones` and `sync-docs-milestones` are green, and the release nightly is green.
8. **Marketing** (Phase 6).

Recovery for a release published under a placeholder `untagged-<hash>` tag is in `MEMORY.md`; re-run `release-bookkeeping.yml` with `tag=<version>` afterwards.

---

## Phase 1: Localization sync

Goal: every feature added this cycle is localized before release. The loop is **have terms → upload → re-download → translate → upload new terms → re-download clean**. The operator doc is [locale-pipeline.md](locale-pipeline.md).

Done when `npm run locale:translate:list` on `master` lists no locales (`locale/terms/missing/` empty).

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
- [ ] No High-severity GHSA advisory is in draft state (2.4, blocks release)

| # | Step | How | Status |
|---|------|-----|--------|
| 2.1 | Compare the latest successful nightly run's SHA with `master` HEAD. If they differ, run the nightly manually and wait for green | [`build-test-nightly.yml`](../../.github/workflows/build-test-nightly.yml): build, root and subdir suites, setup wizard, locale smoke, upgrade matrix. A manual run takes about 30 to 48 min | ✋ check and trigger / 🤖 run |
| 2.2 | Confirm `Build, Test and Package` on `master` HEAD is completed and green; its `ChurchCRM-<version>.zip` artifact is the released file | [`build-test-package.yml`](../../.github/workflows/build-test-package.yml) runs on every push to `master`, about 36 min | 🤖 run / ✋ check |
| 2.3 | Review the GitHub milestone matching the `package.json` version on `master`; remove any open issue or PR that will not ship | Milestones → `<version>`. [`pr-milestone-stamp.yml`](../../.github/workflows/pr-milestone-stamp.yml) stamps merged PRs and `release-bookkeeping.yml` rolls open items to the next milestone, so the review looks for blockers | ✋ |
| 2.4 | Check for draft High-severity GHSA advisories at https://github.com/ChurchCRM/CRM/security/advisories (state=draft, severity=High). Fix and publish first, or defer the release | | ✋ |

Notes:

- Run 2.1 after Phase 1, because every locale PR merge moves HEAD and invalidates the nightly SHA.
- The nightly cron is `0 3 * * *`; scheduled runs start about 08:00 to 08:30 UTC because of GitHub queue delay.
- A red nightly blocks the release until its cause is fixed.
- `release-publish.yml` takes the newest successful `master` push build whose `package.json` version matches and uploads that build's ZIP. The agent gate in `release-management.md` checks HEAD = build SHA = nightly SHA.
- Master pushes use per-commit concurrency, so several builds run in parallel after a burst of merges; wait for the HEAD build. Pushes touching only ignored paths (`locale/terms/**`, `locale/messages.po`, docs, `.agents/**`) do not build, so the latest build can lag HEAD and its ZIP is still correct.

## Phase 3: Cut the release

| # | Step | How | Status |
|---|------|-----|--------|
| 3.1 | Run **Release & Start Next** with `next_version`. It creates the draft from the tested build's ZIP, uploads the `release-notes-context-<version>` artifact, bumps `master` to `next_version` and opens the `Start <next> release` PR. It requires green build, nightly and marketing capture on the branch tip and refuses to replace an existing release | [`release-publish.yml`](../../.github/workflows/release-publish.yml) → [`release-prepare.yml`](../../.github/workflows/release-prepare.yml), about 2 to 3 min | ✋ trigger / 🤖 run |
| 3.2 | Release notes: an agent reads the context artifact, writes user-facing notes following [`release-notes.md`](../../.agents/skills/churchcrm/release-notes.md) (grouped by user impact, verified language count), and after approval applies them with `gh release edit <tag> --tag <tag> --notes-file …` | Agent and skill | 🧑‍💻 |
| 3.3 | Merge the `Start <next> release` PR before publishing. Bookkeeping stops if `master` still carries the released version, and `pr-milestone-stamp.yml` stamps new merges into the next milestone | PR from `build/<next>` | ✋ |
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
| 4.6 | Docs milestones: ensures `<version>` and next exist in `ChurchCRM/docs.churchcrm.io`, keeping held docs PRs on the released one | job `sync-docs-milestones`; needs the `DOCS_RELEASE_TOKEN` secret | 🤖 |
| 4.7 | Post-release nightly: the full suite, including the upgrade matrix, runs against the published release | `build-test-nightly.yml` on `release: published` | 🤖 |
| 4.8 | Verify draft advisories with `patched_version` = the released version are public; publish any that are not | https://github.com/ChurchCRM/CRM/security/advisories | ✋ (same day) |

Notes:

- The in-app release notes (4.2, 4.3) are the widest-reaching copy in the release; keep them user-facing.
- `changelog/<version>.md` appears when `release-bookkeeping.yml` finishes; the wizard link resolves after that.
- Publishing with `prerelease=true` ships a release candidate without notifying admins.
- Re-run a failed bookkeeping job with `workflow_dispatch` (`tag`). The workflow rejects non-version tags and stops if `master` has not been bumped.
- The 4.4 commit touches only `.md` files, which Build/Test/Package ignores, so it starts no build.
- Merging the `Start <next> release` PR starts a normal `master` build; it creates no draft release.

## Phase 5: Open the next cycle

Step 3.3 covers it: merge the `Start <next> release` PR before publishing, because bookkeeping reads the next version from `master` at publish time, and `pr-milestone-stamp.yml` then stamps new merges into the next milestone.

## Phase 6: Marketing push

Starts after Publish (3.4) and runs about a week.

| # | Step | How | Status |
|---|------|-----|--------|
| 6.1 | Check the marketing visuals are published at https://cdn.churchcrm.io | [`marketing-capture-assets.yml`](../../.github/workflows/marketing-capture-assets.yml) runs on the release commit as a gate in 3.1. On Publish, `release-bookkeeping` job `publish-visuals` starts `Publish visuals` in `ChurchCRM/visuals` with that capture run (`DOCS_RELEASE_TOKEN` needs Actions: write there). If it warned, run `Publish visuals` by hand with the capture run id | 🤖 / ✋ fallback |
| 6.2 | Release the docs. In `ChurchCRM/docs.churchcrm.io`: (1) set `crm-release.json` on `release-docs/<version>` to the released version and push, (2) merge the `docs: ChurchCRM <version>` pull request into `main`, (3) create `release-docs/<next version>` from `main` (command in the docs repo `DOCS_GUIDE.md`) | Docs PRs for the release were merged into `release-docs/<version>` as approved, and their CRM tracking issues closed then | ✋ |
| 6.3 | Launch-day announcements: Discord, "shipped, please retest" comments on fixed issues, social posts | [Release Announcement skill](../../.agents/skills/churchcrm/release-announcement.md) steps 1 and 2; [`social-media-release.md`](../../.agents/skills/churchcrm/social-media-release.md) | 🧑‍💻 |
| 6.4 | For a release with new features, write a blog post | Blog PR in `ChurchCRM.io` | ✋ |
| 6.5 | A few days later, share the blog post on social media | | ✋ |
| 6.6 | Week-long marketing push | | ✋ |
| 6.7 | Evaluate the campaign | Signals: GA4 on churchcrm.io (`utm_campaign=release-<tag>` on Discord links, `github_content` on README/CONTRIBUTING links), GitHub release ZIP download counts, PostHog `crm_version` for opted-in installs, social platform analytics | ✋ |

Marketing judgment calls stay with the maintainer: which feature leads, whether a blog is warranted, and the week's cadence. An agent can draft the week's plan (launch posts, blog draft, blog share, follow-ups) from the final release notes.
