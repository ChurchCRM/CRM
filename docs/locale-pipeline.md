# Locale Pipeline

How new UI strings become translations in every supported language before a release.

All workflows, branches and the rules they follow: [github-actions.md](github-actions.md).

**Goal:** every locale in `src/locale/locales.json` is fully translated for every release. The marketing site's languages do not rank or exclude CRM locales.

## Two flows

**Terms flow** manages strings. No translation happens in it.

```
PR ─merge─► master ─► Locale: terms ─► terms PR ─merge─► master ─► Locale: sync ─► download PR ─merge─► master
                                                                    settle 30 min → upload terms → download
```

**Translate flow** fills the gaps the download leaves in `locale/terms/missing/`.

```
locale/terms/missing/ ─► translation agent (LLM) ─► locale/translate/* push ─► Locale: upload translations ─► Locale: sync ─► download PR ─merge─► master
                                                                                                              (download only)
```

Done for a release when `locale/terms/missing/` on `master` is empty for every locale.

## Branches

The branch name says what the branch holds. Uploading is a workflow step, never a branch.

| Branch | Holds | Made by | Merged? |
|---|---|---|---|
| `locale/terms/<version>-<YYYYMMDD-HHMMSS>` | New source strings in `locale/messages.po` | `Locale: terms` (`locale-generate-terms.yml`) | Yes, after review |
| `locale/download/<version>` | What POEditor returned: `src/locale` and the `locale/terms/missing/` batches | `Locale: sync` (`locale-sync-poeditor.yml`) | Yes, after a quick scan |
| `locale/translate/<version>-<YYYY-MM-DD>-<HHMMSS>` | Translated batches | the translation agent (`/locale-translate`) | **No.** The push is the upload. Delete it once its uploads are green |

| Workflow | Flow | Does |
|---|---|---|
| `Locale: terms` | terms | Extracts new strings and opens the terms PR |
| `Locale: sync` | terms, and the download of translate | Uploads terms (skipped when dispatched with `upload_terms=false`), then downloads and opens the download PR |
| `Locale: upload translations` | translate | Uploads the locales a translate push changed, then starts `Locale: sync` download-only |

### Why it does not loop

Each trigger is scoped to what only its own stage produces.

| Merge to `master` | Touches | Starts |
|---|---|---|
| terms PR | `locale/messages.po`, `src/locale/i18n/` | `Locale: sync` (path `locale/messages.po`), after a 30 minute settle. Not `Locale: terms`: `src/locale/i18n/**` is excluded from its filter |
| download PR | `src/locale/i18n/`, `src/locale/textdomain/`, `locale/terms/missing/` | nothing: no `locale/messages.po`, and `Locale: terms` ignores those paths |
| translate branch | never merged | its push runs `Locale: upload translations` once, which starts the download once |

Every step is idempotent: the terms upload does nothing when POEditor already matches `messages.po`, and the translation upload skips empty and already-accepted terms. So a manual, daily or dispatched run is always safe.

A PR merged with `GITHUB_TOKEN` starts no workflows. Merge terms and download PRs as yourself (or a GitHub App, see #10093) or the next stage will not start.

Legacy branches `locale/update-<timestamp>`, `locale/<version>` and `locales/<version>-…` predate these names. The branch manager still recognises the `locales/` form; none of them trigger a workflow.

### Rules for translation agents

- Start every session on a new `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`). Never reuse one.
- Commit and push after **every** locale. The push is both the backup and the upload.
- Push with your own credentials. Pushes made with the Actions `GITHUB_TOKEN` start no workflows, so nothing uploads; list those locales for a manual **Locale: upload translations** run instead.
- Fill every key a plural entry has; never add, remove or `|`-join forms.

## Workflows

| Workflow | Trigger | Does | Needs |
|---|---|---|---|
| `locale-generate-terms.yml` | push to `master` touching `src/**` except `src/locale/i18n/**` and `src/locale/textdomain/**`, manual | runs `npm run locale:build`, opens the terms PR | — |
| `locale-sync-poeditor.yml` | push to `master` touching `locale/messages.po` (after a 30 minute settle), daily 00:00 UTC, manual, dispatched by the upload workflow with `upload_terms=false` | 1. `poeditor-upload-terms.js` (skips when POEditor matches; verifies after; refuses a large deletion), 2. downloads JSON/PO/MO + missing batches and opens the download PR | `POEDITOR_TOKEN` (the one secret for download, upload and terms) |
| `locale-upload-missing.yml` | push to `locale/translate/**` (`locale/terms/missing/**`), manual (`locales`, `sync` inputs) | runs `poeditor-upload-missing.js --yes --no-download` for the changed locales, then dispatches the sync | `POEDITOR_TOKEN`, `actions: write` |

The upload workflow runs one at a time (`concurrency: locale-upload-missing`) because POEditor accepts one upload per ~20 seconds. Each push starts its own download, which queues behind any download already running.

## Merge bursts

A merged terms PR does not sync at once. The sync run's first job, `settle`, waits 30 minutes before any other job starts, and every newer push to `master` touching `locale/messages.po` cancels that wait and starts it again. Several terms merges in a row therefore produce one upload and one download, from the newest `master`. Manual, scheduled and dispatched runs skip the wait; to sync immediately after a merge, run `Locale: sync` by hand. The download job queues behind any run already syncing, so two syncs never overlap. A run always works from the newest `master` once it holds that lock, and refuses to start from any other branch or tag, because the terms upload deletes POEditor terms that are missing from `locale/messages.po`. The publish step skips when the download branch already holds a newer download, so an older run can never overwrite a newer one.

## Terms upload guard

`sync_terms` deletes each removed term and its translations in every language. After the upload, `poeditor-upload-terms.js` reads POEditor's terms again and fails unless they match `locale/messages.po` exactly, so a term removed from the file is really gone before the download starts. Because that delete is destructive, it reads POEditor's terms first and refuses to upload when `locale/messages.po` is empty or would delete more than 2% of the project (at least 25 terms). For an intended cleanup, run the sync manually with `max_term_deletions` set, or run the script with `--max-deletions <n>`; `--dry-run` shows the plan.

## Plurals

Two kinds, told apart by `locale/messages.po` (`locale/scripts/lib/poeditor-plurals.js`, tested by `npm run locale:test`):

| Kind | Source | Uploaded as |
|---|---|---|
| gettext plural | PHP `ngettext`, `msgid_plural` | `{ "term": { "one": "…", "few": "…", "other": "…" } }`, one key per slot the language has in POEditor |
| i18next pair | `{{count}}` keys, `msgctxt "one"` / `"other"` | `{ "one": { "term": "…" }, "other": { "term": "…" } }` |

Never pipe-join forms: POEditor stores `"A|B"` whole in the first slot (gettext) or drops it (i18next). See #10062.

## Planned

- Auto-merge the terms and download PRs behind a path-allowlist and file-validity check (needs a GitHub App token, since PRs merged with `GITHUB_TOKEN` start no follow-up workflow).
- Start the translation agent from the merged download PR instead of a fixed schedule.
- Release check: fail readiness when `locale/terms/missing/` is not empty.
