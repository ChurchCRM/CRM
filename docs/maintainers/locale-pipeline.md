# Locale Pipeline

How new UI strings become translations in every supported language before a release.

All workflows, branches and the rules they follow: [github-actions.md](github-actions.md).

**Goal:** every locale in `src/locale/locales.json` is fully translated for every release. The marketing site's languages do not rank or exclude CRM locales.

## Two flows

**Terms flow** manages strings. No translation happens in it.

```
code PRs ─merge─► master ─► Locale: extract terms ─► terms PR ─merge─► master ─► Locale: sync POEditor ─► download PR ─auto-merge─► master
                            wait 15 min (each merge              (person)        upload terms → download
                            restarts it), extract
```

The one pause is at the start: a burst of code merges becomes one terms PR, and the sync after its merge runs at once. Skip the pause with a manual run of `Locale: extract terms` (it cancels a running wait), or `npm run locale:release -- --go` before a release.

**Translate flow** fills the gaps the download leaves in `locale/terms/missing/`.

```
locale/terms/missing/ ─► translation agent (LLM) ─► locale/translate/* push ─► Locale: upload translations ─► Locale: sync POEditor ─► download PR ─merge─► master
                                                                                                              (download only)
```

Done for a release when `npm run locale:translate:list` on `master` lists no locales. A batch counts only while it has an empty value; the filled regional English batches (`en` is en-GB, `en-au`, `en-ca`; all `skip_audit`) stay.

## Branches

The branch name says what the branch holds. Uploading is a workflow step, never a branch.

| Branch | Holds | Made by | Merged? |
|---|---|---|---|
| `locale/terms/<version>` | New source strings in `locale/messages.po`. One rolling branch: a later burst updates the open terms PR instead of opening another | `Locale: extract terms` (`locale-generate-terms.yml`) | Yes, after review |
| `locale/download/<version>` | What POEditor returned: `src/locale` and the `locale/terms/missing/` batches | `Locale: sync POEditor` (`locale-sync-poeditor.yml`) | The workflow squash-merges it when every JSON file parses and every path is a download file. Invalid JSON or any other path leaves the PR open |
| `locale/translate/<version>-<YYYY-MM-DD>-<HHMMSS>` | Translated batches | the translation agent (`/locale-translate`) | **No.** The push is the upload. Delete it once its uploads are green |

| Workflow | Flow | Does | PR title | Run shows as |
|---|---|---|---|---|
| `Locale: extract terms` | terms | Waits for the merge burst, extracts, opens or updates the terms PR | `locale: new terms from code (+N / -M) - <date>` | `Locale terms: wait for more merges, then extract` / `… extract now (manual, no wait)` |
| `Locale: sync POEditor` | terms, and the download of translate | Uploads terms (skipped when dispatched with `upload_terms=false`), then downloads and opens and merges the download PR | `locale: download translations from POEditor - <date>` | `Locale sync: upload terms + download translations (terms PR merged)`, `… download translations only (after a translation upload)`, `… daily …`, `… manual` |
| `Locale: upload translations` | translate | Uploads the locales a translate push changed, then starts the sync download-only | none (the branch is never merged) | `Locale upload translations: <branch>` |

### Why it does not loop

Each trigger is scoped to what only its own stage produces.

| Merge to `master` | Touches | Starts |
|---|---|---|
| terms PR | `locale/messages.po`, `src/locale/i18n/` | `Locale: sync POEditor` (path `locale/messages.po`), at once. Not `Locale: extract terms`: `src/locale/i18n/**` is excluded from its filter |
| download PR | `src/locale/i18n/`, `src/locale/textdomain/`, `locale/terms/missing/` | nothing: no `locale/messages.po`, and `Locale: extract terms` ignores those paths |
| translate branch | never merged | its push runs `Locale: upload translations` once, which starts the download once |

Every step is idempotent: the terms upload does nothing when POEditor already matches `messages.po`, and the translation upload skips empty and already-accepted terms. So a manual, daily or dispatched run is always safe.

A PR merged with `GITHUB_TOKEN` starts no workflows. Merge a terms PR as yourself (or a GitHub App) so `Locale: sync POEditor` starts. The download PR is the exception: `Locale: sync POEditor` squash-merges it with `GITHUB_TOKEN`. That merge starts nothing, which is what the download stage wants. An invalid JSON file or a path outside `src/locale/i18n/`, `src/locale/textdomain/<locale>/LC_MESSAGES/messages.po|mo`, and `locale/terms/missing/<locale>/*.json` leaves the PR open.

### Rules for translation agents

- Start every session on a new `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`).
- Commit and push after **every** locale. The push is both the backup and the upload.
- Push with your own credentials. Pushes made with the Actions `GITHUB_TOKEN` start no workflows, so nothing uploads; list those locales for a manual **Locale: upload translations** run instead.
- Fill every key a plural entry has; never add, remove or `|`-join forms.

## Workflows

| Workflow | Trigger | Does | Needs |
|---|---|---|---|
| `locale-generate-terms.yml` | push to `master` touching `src/**` except `src/locale/i18n/**` and `src/locale/textdomain/**` (after the wait), manual (no wait) | runs `npm run locale:build`, writes the terms report (`locale-terms-diff.js`: PR body, run summary, `locale-terms-report` artifact), opens or updates the terms PR | repo variable `LOCALE_TERMS_WAIT_MINUTES` (optional, default 15) |
| `locale-sync-poeditor.yml` | push to `master` touching `locale/messages.po` (at once), daily 00:00 UTC, manual, dispatched by the upload workflow with `upload_terms=false` | 1. `poeditor-upload-terms.js` (skips when POEditor matches; verifies after; refuses a large deletion), 2. downloads JSON/PO/MO + missing batches, opens the download PR, and squash-merges it when `locale-download-gate.js` passes | `POEDITOR_TOKEN` (the one secret for download, upload and terms) |
| `locale-upload-missing.yml` | push to `locale/translate/**` (`locale/terms/missing/**`), manual (`locales`, `sync` inputs) | runs `poeditor-upload-missing.js --yes --no-download` for the changed locales, then dispatches the sync | `POEDITOR_TOKEN`, `actions: write` |

The upload workflow runs one at a time (`concurrency: locale-upload-missing`) because POEditor accepts one upload per ~20 seconds. Each push starts its own download, which queues behind any download already running.

## Merge bursts

The pause sits at the start of the terms flow. `Locale: extract terms` waits 15 minutes (repo variable `LOCALE_TERMS_WAIT_MINUTES`, 0 to 120) before it extracts, and every newer push to `master` touching `src/**` cancels that wait and starts it again. A burst of code merges therefore produces one extraction and one terms PR; a later burst updates the same PR while it is open. The extraction checks out the newest `master` after the wait, not the commit that started it.

`Locale: sync POEditor` does not wait: the terms PR is a person's merge, so it already comes once per burst. Its download job queues behind any run already syncing, so two syncs never overlap. A run always works from the newest `master` once it holds that lock, and refuses to start from any other branch or tag, because the terms upload deletes POEditor terms that are missing from `locale/messages.po`. The publish step skips when the download branch already holds a newer download, so an older run can never overwrite a newer one.

To skip the pause (a release, or you know every PR is in): run `Locale: extract terms` by hand, or `npm run locale:release -- --go`. The manual run cancels the running wait.

## Reviewing a terms PR

The body opens with the counts and the next step, then:

- **Code merges in this batch**: the commits since the last terms merge.
- **Needs a look**: new strings that are empty, have leading or trailing whitespace or double spaces, have no letters, contain HTML tags, look like code or a variable name, have unbalanced `{{ }}`, are over 200 characters, or differ from an existing term only by case or punctuation; and new strings that reword a removed one, whose translations are lost unless the old term is moved in POEditor (or the code keeps the old wording).
- **New terms** with where each is in the code (`git grep`, since `messages.po` has no locations), and **Removed terms**, marking those merged into an existing near-duplicate.

Fix an odd string in the code, not in the terms PR; the next extraction updates the PR. Bodies over GitHub's limit are truncated; the full list is in the run summary and the `locale-terms-report` artifact. Run it locally with `node locale/scripts/lib/locale-terms-diff.js --base HEAD` after `npm run locale:build`.

## Release

`npm run locale:release` reports what is running, which locale PRs are open, and what is untranslated, with the next step. `npm run locale:release -- --go` extracts terms now, stops at the terms PR if there is one (merge it and run again), otherwise runs `Locale: sync POEditor`, waits for its download PR to merge, pulls, and reports what is left to translate. It exits 0 when locales are release-ready. It never merges the terms PR.

## Terms upload guard

`sync_terms` deletes each removed term and its translations in every language. After the upload, `poeditor-upload-terms.js` reads POEditor's terms again and fails unless they match `locale/messages.po` exactly, so a term removed from the file is really gone before the download starts. Because that delete is destructive, it reads POEditor's terms first and refuses to upload when `locale/messages.po` is empty or would delete more than 2% of the project (at least 25 terms). For an intended cleanup, run the sync manually with `max_term_deletions` set, or run the script with `--max-deletions <n>`; `--dry-run` shows the plan.

## Plurals

Two kinds, told apart by `locale/messages.po` (`locale/scripts/lib/poeditor-plurals.js`, tested by `npm run locale:test`):

| Kind | Source | Uploaded as |
|---|---|---|
| gettext plural | PHP `ngettext`, `msgid_plural` | `{ "term": { "one": "…", "few": "…", "other": "…" } }`, one key per slot the language has in POEditor |
| i18next pair | `{{count}}` keys, `msgctxt "one"` / `"other"` | `{ "one": { "term": "…" }, "other": { "term": "…" } }` |

Send each form under its own slot or context key; a pipe-joined `"A|B"` is stored whole in the first slot (gettext) or dropped (i18next).

## Planned

- Auto-merge the terms PR behind a path-allowlist (needs a GitHub App token, since a `GITHUB_TOKEN` merge starts no `Locale: sync POEditor`).
- Start the translation agent from the merged download PR instead of a fixed schedule.
- Release check: fail readiness when `npm run locale:translate:list` lists a locale.
