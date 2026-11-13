# Locale Pipeline

How new UI strings become translations in every supported language before a release.

All workflows, branches and the rules they follow: [github-actions.md](github-actions.md). Design and release impact: [locale-design.md](locale-design.md).

**Goal:** every locale in `src/locale/locales.json` is fully translated for every release. The marketing site's languages do not rank or exclude CRM locales.

**Source of truth:** the repo. Translations live in `src/locale/i18n/<locale>.json`; each locale's `src/locale/textdomain/<locale>/LC_MESSAGES/messages.po` and `.mo` are generated from that JSON. There is no outside translation service ([#11416](https://github.com/ChurchCRM/CRM/issues/11416) retired POEditor).

## Two flows

**Terms flow** manages strings. No translation happens in it.

```
code PRs ─merge─► master ─► Locale: extract terms ─────────────────────► terms PR ─merge─► master
                            wait 15 min (each merge restarts it),         (person)
                            npm run locale:build (extract, prune, queue)
```

The one pause is at the start: a burst of code merges becomes one terms PR. Skip the pause with a manual run of `Locale: extract terms` (it cancels a running wait), or `npm run locale:release -- --go` before a release.

The terms PR carries `locale/messages.po`, the refreshed queue `locale/terms/missing/` (every term still to translate), and, when strings were removed, the locale files without them (`--prune` drops their translations and rebuilds each `.po`/`.mo`). Review and merge it first; translating starts from the queue on `master`.

**Translate flow** fills what the repo is missing, as a normal PR.

```
npm run locale:missing ─► locale/terms/missing/ ─► translation agent (/locale-translate) ─► npm run locale:apply ─► translate PR ─merge─► master
(repo vs messages.po)     (checked-in queue)                                              (JSON, .po, .mo)       (person)
```

Done for a release when `npm run locale:missing` reports nothing to translate on `master` (`npm run locale:release` checks it).

## Scripts

| Command | Does |
|---|---|
| `npm run locale:build` | Extracts every string from the code into `locale/messages.po`, then runs `locale:apply --prune` and `locale:missing`, leaving the tree ready to commit (CI runs it; needs the Docker DB) |
| `npm run locale:missing [-- --locale de,fr]` | Writes each locale's untranslated terms to `locale/terms/missing/<code>/<code>-<n>.json`. Filled values already in a batch are kept. `--term "<new msgid>"` also queues a string that is not in `messages.po` yet |
| `node locale/scripts/locale-translate.js --list / --prefill / --export / --apply-bulk` | What the translation agent uses to read and fill the batches (validates placeholders, script, plural forms) |
| `npm run locale:apply [-- --locale de]` | Moves every filled batch term into the locale JSON, rebuilds the locale `.po` and `.mo`, and empties the batch. Rejected values stay in the batch with the reason |
| `npm run locale:apply -- --prune` | Also drops translations of terms no longer in `messages.po` (`locale:build` runs this) |
| `npm run locale:apply -- --check` | Writes nothing; fails when a locale `.po`/`.mo` does not match its JSON |
| `npm run locale:audit` | Completeness per locale from the repo: `src/locale/poeditor.json` (read by the app for the translation %) and `locale/poeditor-audit.md` |
| `npm run locale:release [-- --go]` | Release check, below |
| `npm run locale:test` | Unit tests for the locale scripts |

## Branches

| Branch | Holds | Made by | Merged? |
|---|---|---|---|
| `locale/terms/<version>` | New source strings in `locale/messages.po`, and the locale files without removed strings. One rolling branch: a later burst updates the open terms PR instead of opening another | `Locale: extract terms` (`locale-generate-terms.yml`) | Yes, after review |
| `locale/translate/<version>-<YYYY-MM-DD>-<HHMMSS>` | Translations: `src/locale/i18n/<locale>.json` and the rebuilt `.po`/`.mo` | the translation agent (`/locale-translate`) | Yes, as a normal PR after review |

`locale/download/<version>` branches came from the retired POEditor download and are no longer made.

### Why it does not loop

`Locale: extract terms` ignores `src/locale/i18n/**` and `src/locale/textdomain/**`, so merging a terms PR or a translate PR never starts another extraction.

### Rules for translation agents

- Start every session on a new `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`).
- Run `npm run locale:missing` first. The queue is checked in, so this only refreshes it to match the checkout.
- After every locale, `node locale/scripts/locale-branch-manager.js --commit-and-push --locale <code> …` writes it into the locale files (`locale:apply`) and commits and pushes that locale's JSON, `.po` and `.mo`, and its queue folder `locale/terms/missing/<code>/` (the applied terms leave it). The push is the backup; the PR is the delivery.
- Fill every key a plural entry has; never add, remove or `|`-join forms.

## Rewording a string

Translations are keyed by the English msgid, so rewording one drops its translations everywhere. Re-translate it in the same PR:

1. Change the string in the code.
2. `npm run locale:missing -- --term "New wording"` queues it for every locale.
3. Translate (`/locale-translate`), then `npm run locale:apply`. The JSON gets the new key at once (JS uses it); the `.po`/`.mo` pick it up in the next terms PR, which also drops the old key.

## Workflows

| Workflow | Trigger | Does | Needs |
|---|---|---|---|
| `locale-generate-terms.yml` | push to `master` touching `src/**` except `src/locale/i18n/**` and `src/locale/textdomain/**` (after the wait), manual (no wait) | runs `npm run locale:build`, writes the terms report (`locale-terms-diff.js`: PR body, run summary, `locale-terms-report` artifact), opens or updates the terms PR | repo variable `LOCALE_TERMS_WAIT_MINUTES` (optional, default 15) |
| `locale-sync-poeditor.yml`, `locale-upload-missing.yml` | manual only | **Retired.** Kept until the follow-up PR deletes them. Do not run them: a POEditor download overwrites translations made in the repo | `POEDITOR_TOKEN` |

## Merge bursts

`Locale: extract terms` waits 15 minutes (repo variable `LOCALE_TERMS_WAIT_MINUTES`, 0 to 120) before it extracts, and every newer push to `master` touching `src/**` cancels that wait and starts it again. A burst of code merges therefore produces one extraction and one terms PR; a later burst updates the same PR while it is open. The extraction checks out the newest `master` after the wait, not the commit that started it.

To skip the pause (a release, or you know every PR is in): run `Locale: extract terms` by hand, or `npm run locale:release -- --go`. The manual run cancels the running wait.

## Reviewing a terms PR

The body opens with the counts and the next step, then:

- **Code merges in this batch**: the commits since the last terms merge.
- **Needs a look**: new strings that are empty, have leading or trailing whitespace or double spaces, have no letters, contain HTML tags, look like code or a variable name, have unbalanced `{{ }}`, are over 200 characters, or differ from an existing term only by case or punctuation; and new strings that reword a removed one, whose translations are lost unless the PR that reworded it re-translated it (see above).
- **New terms** with where each is in the code (`git grep`, since `messages.po` has no locations), and **Removed terms**, marking those merged into an existing near-duplicate.

Fix an odd string in the code, not in the terms PR; the next extraction updates the PR. Bodies over GitHub's limit are truncated; the full list is in the run summary and the `locale-terms-report` artifact. Run it locally with `node locale/scripts/lib/locale-terms-diff.js --base HEAD` after `npm run locale:build`.

## Release

`npm run locale:release` reports what is running, whether a terms PR is open, and what is untranslated in this checkout, with the next step. `npm run locale:release -- --go` extracts terms now, stops at the terms PR if there is one (merge it, pull, and run again), otherwise reports what is left to translate. It exits 0 when locales are release-ready. It never merges anything.

## Plurals

Two kinds, told apart by `locale/messages.po` (`locale/scripts/lib/locale-files.js`, tested by `npm run locale:test`):

| Kind | Source | In the locale JSON | In the locale `.po` |
|---|---|---|---|
| gettext plural | PHP `ngettext`, `msgid_plural` | `{ "term": { "one": "…", "few": "…", "other": "…" } }`, the forms the locale already uses (CLDR for a new locale) | `msgstr[0..n]` in that order |
| i18next pair | `{{count}}` keys, `msgctxt "one"` / `"other"` | `{ "term": { "one": "…", "other": "…" } }` | one entry per `msgctxt` |

Send each form under its own key; never pipe-join `"A|B"`.

## Planned

- Auto-merge the terms PR behind a path-allowlist.
- Start the translation agent from the merged terms PR instead of by hand.
