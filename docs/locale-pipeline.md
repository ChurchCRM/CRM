# Locale Pipeline

How new UI strings become translations in every supported language before a release.

**Goal:** every locale in `src/locale/locales.json` is fully translated for every release. The marketing site's languages do not rank or exclude CRM locales.

## The loop

```
have terms → upload terms → download → translate → upload translations → download clean
```

| Step | What happens | Where |
|---|---|---|
| Have terms | A merge to `master` touching `src/**` extracts new strings into `locale/messages.po` and opens an "Update locale strings" PR | `locale-generate-terms.yml` |
| Upload terms | `locale/messages.po` is imported into POEditor; removed terms are deleted | **Manual** (POEditor UI) — [planned](#planned) |
| Download | POEditor translations and the per-locale missing-term batches land in a "locale: update translations from POEditor" PR | `locale-sync-poeditor.yml` (daily 00:00 UTC, or run manually) |
| Translate | An agent translates `locale/terms/missing/<locale>/*.json` on a `locale/translate/**` branch, one commit and push per locale | `/locale-translate` |
| Upload translations | Each push uploads the locales it changed, then starts the sync | `locale-upload-missing.yml` |
| Download clean | The sync PR brings the new translations back; the loop ends when `locale/terms/missing/` is empty | `locale-sync-poeditor.yml` |

Done for a release when `locale/terms/missing/` on `master` is empty for every locale.

## Branches

The branch name says which stage produced it.

| Branch | Created by | Merged? |
|---|---|---|
| `locale/update-<timestamp>` | `locale-generate-terms.yml` (new source strings) | Yes, after review |
| `locale/<version>` | `locale-sync-poeditor.yml` (translations + missing batches from POEditor) | Yes, after a quick scan |
| `locale/translate/<version>-<YYYY-MM-DD>-<HHMMSS>` | `locale-branch-manager.js --init` (agent translation session) | **No.** Pushing it uploads to POEditor; the sync PR carries the result to `master`. Delete it once its uploads are green |

Legacy agent branches (`locales/<version>-…`) are still recognised by the branch manager but do not trigger the upload workflow.

### Rules for translation agents

- Start every session on a new `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`). Never reuse one.
- Commit and push after **every** locale. The push is both the backup and the upload.
- Push with your own credentials. Pushes made with the Actions `GITHUB_TOKEN` start no workflows, so nothing uploads; list those locales for a manual **Locale: upload missing terms** run instead.
- Fill every key a plural entry has; never add, remove or `|`-join forms.

## Workflows

| Workflow | Trigger | Does | Needs |
|---|---|---|---|
| `locale-generate-terms.yml` | push to `master` (`src/**`), manual | runs `npm run locale:build`, opens the terms PR | — |
| `locale-sync-poeditor.yml` | daily 00:00 UTC, manual, dispatched by the upload workflow | uploads any translated batches on `master`, downloads JSON/PO/MO + missing batches, opens the sync PR | `POEDITOR_TOKEN` |
| `locale-upload-missing.yml` | push to `locale/translate/**` (`locale/terms/missing/**`), manual (`locales`, `sync` inputs) | runs `poeditor-upload-missing.js --yes --no-download` for the changed locales, then dispatches the sync | `POEDITOR_TOKEN`, `actions: write` |

The upload workflow runs one at a time (`concurrency: locale-upload-missing`) because POEditor accepts one upload per ~20 seconds. The sync cancels an in-progress run when a new one starts, so a burst of pushes ends in a single sync PR.

## Plurals

Two kinds, told apart by `locale/messages.po` (`locale/scripts/lib/poeditor-plurals.js`, tested by `npm run locale:test`):

| Kind | Source | Uploaded as |
|---|---|---|
| gettext plural | PHP `ngettext`, `msgid_plural` | `{ "term": { "one": "…", "few": "…", "other": "…" } }`, one key per slot the language has in POEditor |
| i18next pair | `{{count}}` keys, `msgctxt "one"` / `"other"` | `{ "one": { "term": "…" }, "other": { "term": "…" } }` |

Never pipe-join forms: POEditor stores `"A|B"` whole in the first slot (gettext) or drops it (i18next). See #10062.

## Planned

- Upload `locale/messages.po` to POEditor automatically when the terms PR merges, with a guard that refuses a large term deletion.
- Auto-merge the terms and sync PRs behind a path-allowlist and file-validity check (needs a GitHub App token, since PRs merged with `GITHUB_TOKEN` start no follow-up workflow).
- Start the translation agent from the merged sync PR instead of a fixed schedule.
- Rename the terms and sync branches to `locale/terms/<version>` and `locale/sync/<version>`.
- Release check: fail readiness when `locale/terms/missing/` is not empty.
