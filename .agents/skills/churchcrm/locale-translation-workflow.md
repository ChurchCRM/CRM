---
title: "Locale Translation Workflow"
intent: "Complete guide for translating missing UI strings across 40+ locales with cloud-safe durability and impact-based prioritization"
tags: ["i18n", "localization", "translation", "release"]
prereqs: ["[[i18n-localization]]"]
complexity: "intermediate"
---

# Locale Translation Workflow

The workflow for translating ChurchCRM UI terms across all supported locales. `/locale-translate` (`.claude/commands/locale-translate.md`) is the operational command reference; this card gives the rules behind it. Pipeline overview: [`docs/locale-pipeline.md`](../../../docs/locale-pipeline.md).

## Overview

1. **Translate**: Claude translates missing terms per locale with church-appropriate vocabulary.
2. **Upload**: each locale is validated and uploaded to POEditor for human review.
3. **Download**: a GitHub Action fetches approved translations and opens a PR.

Every translated locale is persisted in three places (local commit, remote branch, POEditor) before the next locale starts, so a cloud session timeout loses nothing.

```bash
/locale-translate --list     # locales with missing terms
/locale-translate --all      # translate, commit, push, upload each locale
```

## Safety Rules

1. **Fresh branch per run.** `/locale-translate --init` creates `locale/translate/{VERSION}-{YYYY-MM-DD}-{HHMMSS}` (e.g. `locale/translate/7.2.0-2026-04-22-174530`). Pushing to it runs `locale-upload-missing.yml`. Unique timestamps avoid collisions between sessions.
2. **Commit after every locale** (or language-family batch):
   ```bash
   git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
   git commit -m "locale: translate <code> (<language>, <N> terms)"
   ```
3. **Push after every commit:** `git push origin $(git branch --show-current)`.
4. **The push uploads to POEditor.** `locale-upload-missing.yml` uploads the changed locales and starts the POEditor sync. Upload by hand (`node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`) when that run failed or the push used the Actions `GITHUB_TOKEN`.
5. **After a session timeout, run `/locale-translate --all` again.** It cuts a new branch, skips translated locales, and POEditor skips uploaded terms.

## Planning

Order locales by speaker reach and group variants that share vocabulary:

1. Spanish (es, es-MX, es-AR, es-CO, es-SV), Chinese (zh-CN, zh-TW), Portuguese (pt-BR, pt), then hi, fr, ru, id, de, ja, ar
2. Regional depth: sw, am, vi, te, it, ko, ta, th, uk, pl, nl, el, ml
3. Remaining European and smaller locales
4. Single-term stragglers

`/locale-translate --list` gives the current term counts. Telugu (te) and Amharic (am) have large batches: process each separately and confirm every batch file is applied.

## Phase 1: Translate

For each locale:

1. Read untranslated terms from `locale/terms/missing/{LOCALE}/{LOCALE}-N.json`.
2. Translate with the church vocabulary below.
3. Commit and push that locale or family batch immediately.
4. The push uploads it.

Commit message: `locale: translate fr (French - France, 154 terms)`.

### Church Vocabulary

| English | Translation guidance |
|---------|---|
| Members/Users | Congregation / Parishioners (varies by denomination) |
| Groups | Small Groups / Ministries / Cells |
| Giving/Payments | Offerings / Tithes / Contributions (region-specific) |
| Pledge | Financial pledge/commitment |
| Cart (selection) | Selection / Roster |
| Family | Family |

Denomination tone: Catholic (es, it, pt, pl, fr: parish, flock), Orthodox (ru, uk, el: church community), Lutheran (de, sv, nb: congregation), Evangelical (ko, zh: local church, believers). For ja, ar, hi consult native speakers when unsure.

### Technical Terms

- **Embedded in a sentence** ("Enable SMTP relay", "Export as JSON"): translate the whole sentence and keep the acronym spelled the same. These stay spelled the same in every locale: `N/A, name@example.com, @, SMS, SMTP, API, HTTP, HTTPS, JSON, XML, HTML, CSS, E.164, ICS, TLS, BCC, ChurchCRM, Vonage, MailChimp, OpenLP, GitHub, Gravatar, POEditor, MD5`.
- **Standalone token key** (a batch entry whose key is exactly `CSV`, `OFX`, `PDF`, `2FA`, `URL`, or another bare format/protocol acronym that never varies by locale): this is a source bug. Report that it needs unwrapping from `gettext()` / `i18next.t()` (`i18n-localization.md`) and skip it.
- **Standalone word that varies by locale** (`Data`, `Code`, `Auto`, `Access`): translate normally. When the right translation equals the English key, it is valid and recorded in `english-ok.json`.

### Parallel Sub-Agents (10+ locales)

1. Handle small locales (10 terms or fewer) inline, one commit per locale.
2. Dispatch one `general-purpose` sub-agent per language family for larger locales (2-5 related locales; a large standalone locale is its own family). Run at most 4 in parallel.
3. The agent prompt requires the agent to read, translate, and apply all translations before returning; a returned-but-unapplied translation is lost.
4. After each agent returns: commit, push, upload.

### Model Selection & Cost Efficiency

Use Haiku 4.5 for locale translation, from a handful of locales to 40+ with language-family batching.

### Review Checks Before Committing

- Church is not rendered as temple (hi)
- "Upload" is not translated as "Download" (fr)
- Placeholders `%s` / `%d` are reordered only with positional indexes (`%1$s`, `%3$s`, `%2$d`)
- One script per locale (no mixed scripts, e.g. ta, te)
- "email" is not rendered as physical post (zh-CN, zh-TW)

### Batching Strategy

1. Group by language family (Romance, Slavic, Uralic, Asian, ...).
2. One agent per family, 2-5 related locales, at most 4 in parallel.
3. Each agent reads all batch files, translates, and applies all before returning.
4. One commit and push per family batch as each completes.
5. Reorder placeholders only with positional indexes and keep one script per locale.

## Phase 2: Upload to POEditor

```bash
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes   # one locale
npm run locale:upload:missing -- --yes                                  # catch up missed locales
```

| Flag | Purpose |
|------|---------|
| `--locale es,fr,de` | Specific locales (comma-separated) |
| `--yes` / `-y` | Skip prompts (required for agents) |
| `--dry-run` | Show what would upload |

The script:

- discovers every folder in `locale/terms/missing/`
- validates each locale, skipping suspect (identical to key) and empty terms
- shapes plurals from `locale/messages.po` (`locale/scripts/lib/poeditor-plurals.js`, tested by `npm run locale:test`):
  - gettext plural (`msgid_plural`, PHP `%d`): `{ "term": { "one": "…", "few": "…", "other": "…" } }`, one key per slot the batch file shows
  - i18next `{{count}}` term (`msgctxt "one"` / `"other"`): `{ "one": { "term": "…" }, "other": { "term": "…" } }`
  - a batch value still pipe-joined in its first slot is re-slotted when the part count matches, otherwise blanked for re-translation
- uploads with metadata, then refreshes the local missing-term files (accepted terms removed)

A pipe-joined upload (`"A|B"`) lands whole in POEditor's first plural slot, and one sent against a `{{count}}` term is dropped; both leave the term missing.

## Phase 3: Download (Automated)

1. The POEditor team reviews and refines the uploaded drafts.
2. The GitHub Action (scheduled or manual) fetches approved translations and writes:
   - `src/locale/i18n/{locale}.json`
   - `src/locale/textdomain/{locale}/LC_MESSAGES/messages.{po,mo}`
   - `locale/terms/missing/{locale}/` batch files for the next iteration
3. It opens a PR. Review and merge it. `npm run locale:download` runs the same download by hand.

## Data-Loss Matrix

| Scenario | Outcome |
|----------|---------|
| Cloud timeout at locale 20 of 39 | The first 20 are committed, pushed, and in POEditor. A new branch continues with the rest. |
| Local machine crash | Everything completed was pushed. |
| Inspect a past run | `git log origin/locale/translate/{version}-*` |
| Upload failure for one locale | The translation is pushed. Retry with `npm run locale:upload:missing -- --locale <CODE>`. |

## Detecting i18n Anti-Patterns Before `locale:build`

Run these on the source tree before extraction so split sentences are fixed at the source.

```bash
# JS: two i18next.t() calls in one expression (template-literal split)
grep -rn 'i18next\.t(' webpack/ src/skin/js/ 2>/dev/null \
  | awk -F: '{ line=$0; n=split(line,_,"i18next.t("); if (n>2) print }'

# PHP: msgid ending in a preposition/article (runtime suffix appended)
grep -rn "gettext('" src/ | grep -iE "gettext\('[^']{1,40} (in|of|for|to|a|the|an)'\)"
grep '^msgid ' locale/messages.po | grep -iE '" ?(in|of|for|to|a|the|an)"$'

# Short lower-case msgids that read like half a sentence
grep '^msgid "[a-z][a-z ]\{1,30\}"$' locale/messages.po
```

Merge each hit into one parameterised string, e.g. `i18next.t("full sentence {{var}}", { var })`.

## English-OK Allowlist

`locale/terms/english-ok.json` lists, per locale, terms whose translation equals the English key. The uploader skips `value === key` terms as suspect unless they are listed. Valid entries:

- technical acronyms: `SHA1 Hash`, `AM / PM`, `am / pm`, `HTML URL`, `ICS URL`
- brand and tech names: `GitHub`, `ChurchCRM`, `POEditor`, `API`
- words identical in the target language: `in` (de, it, nl), `Minutes` (fr), `Important` (ro), `Manual` (pt-br), country names (fil, id)

`locale-translate.js --apply` and `--apply-bulk` add identical values to the file, and `--prefill` fills listed terms without a model. The file is generated; `locale-branch-manager.js --commit-and-push` stages it with the locale batches, and a plain `git add` includes it explicitly.

## Regional English Spelling Overrides (en-GB / en-AU / en-CA)

Source strings are US-spelled. `behaviour`, `colour`, `centre`, `enrolment` and similar are ordinary translations of the `en` / `en-au` / `en-ca` POEditor languages. `poeditor-downloader.js` regenerates `src/locale/i18n/en_{GB,AU,CA}.json` and `src/locale/textdomain/en_{GB,AU,CA}/LC_MESSAGES/messages.{po,mo}` on each sync, so overrides go in the missing-term batches:

```
locale/terms/missing/en/en-1.json         # en    = English - Great Britain
locale/terms/missing/en-au/en-au-1.json
locale/terms/missing/en-ca/en-ca-1.json
```

Key each entry by the exact US source string; plurals are `{ "one": "...", "other": "..." }`. These variants are `skip_audit: true` in `src/locale/locales.json`, so create the folders by hand. Then run `npm run locale:upload:missing -- --locale en,en-au,en-ca` and let the download job open the sync PR. `en-ca` keeps `-ize` / `Recognized` and takes `-our` / `-re` / `cheque` / `catalogue` / `enrolment`.

## Troubleshooting

- **Resume after interruption:** run `/locale-translate --all` again.
- **Upload fails:** check the POEditor API token in `.env`, then rerun `npm run locale:upload:missing`.
- **Edit a skill on `master` while on a locale branch:** use `git worktree add /tmp/crm-master master`, edit and commit there, push, then `git worktree remove /tmp/crm-master`.

## Helper Scripts (`locale/scripts/`)

| Script | Purpose |
|--------|---------|
| `locale-translate.js` | Read/apply batch files (`/locale-translate`) |
| `locale-branch-manager.js` | Create and manage `locale/*` branches |
| `poeditor-upload-missing.js` | Validate and upload (`npm run locale:upload:missing`) |
| `poeditor-downloader.js` | Download and generate formats (`npm run locale:download`) |
| `locale-config.js` | Shared configuration |

## Related

`/locale-translate` (command reference), `/locale-release` (release pipeline that calls it), `i18n-localization.md`, `git-workflow.md`.
