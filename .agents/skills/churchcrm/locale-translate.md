
# Locale Translate

Translate missing ChurchCRM terms into the target language using church-appropriate ecclesiastical vocabulary.

## Arguments

`$ARGUMENTS` can be:
- `--locale <poEditorCode>` — translate one locale (e.g. `--locale fr`, `--locale af`, `--locale es-MX`)
- `--all` — translate every locale that has untranslated terms
- `--list` — list locales with missing terms and stop (no translation)

## Instructions

### Step 0 — Parse arguments

Read `$ARGUMENTS`. Determine:
- `mode`: `single` if `--locale <code>` is given, `all` if `--all`, `list` if `--list`
- `targetLocale`: the poEditor code if mode is `single`

If no valid argument is given, tell the user to run `/locale-translate --locale <code>` or `/locale-translate --all`, then stop.

### Step 1 — List available locales (always do this first)

Run:
```bash
node locale/scripts/locale-translate.js --list
```

If mode is `list`, print the output and stop.

### Step 2 — Determine which locales to translate

- **single mode**: use just that locale code
- **all mode**: parse the `--list` output to get every locale code shown, then **sort by total term count ascending (smallest first, largest last)**

**Why smallest-first?** Small locales complete quickly and validate the workflow early. Failures on a 13-term locale cost almost nothing; failing halfway through a 1000-term locale wastes significant time. Always finish all small locales before starting large ones.

### Step 3 — For each target locale, get metadata

For each locale code, run:
```bash
node locale/scripts/locale-translate.js --info --locale <code>
```

This returns lightweight JSON with **no term content** — only:
- `name` — human-readable language name
- `countryCode` — two-letter country code
- `batchFiles[]` — each with `path` and `termCount` (number of untranslated terms)

### Step 4 — Apply denomination context

Use the country code to select the right denomination/vocabulary context:

| Country | Context |
|---------|---------|
| EG | **Coptic Orthodox** (Egyptian Arabic) — كنيسة, قداس, رعية, تقدمة. This locale is used almost exclusively by the Coptic Christian community. |
| IT | Roman Catholic — parrocchia, offertorio, fedeli |
| ES, MX, CO, AR, SV | Roman Catholic — parroquia, ofrenda, feligreses |
| FR | Roman Catholic / Ecumenical — paroisse, offrande, fidèles |
| PT, BR | Catholic / Mixed Evangelical |
| PL | Roman Catholic — parafia, ofiara, wierni |
| RO | Romanian Orthodox / Catholic |
| RU | Russian Orthodox — приход, пожертвование, прихожане |
| UA | Ukrainian Orthodox |
| GR | Greek Orthodox — ενορία, προσφορά, ενορίτες |
| DE | Protestant/Lutheran — Gemeinde, Spende, Gemeindemitglieder |
| SE, NO, FI, EE | Lutheran |
| NL | Protestant/Reformed |
| KR | Evangelical/Presbyterian — 교회, 헌금, 성도 |
| TW | Evangelical/Presbyterian |
| CN | Protestant/Three-Self — 教会, 奉献, 会众 |
| GB, AU | Anglican / Ecumenical |
| ET | Ethiopian Orthodox / Protestant |
| IN | Broadly Christian / Non-denominational |
| ID | Protestant/Catholic — jemaat, persembahan, anggota gereja |
| IL | Messianic Jewish/Christian |
| ZA | Mixed Protestant |
| _default_ | Broadly Christian, ecumenical |

### Step 5 — Church-specific vocabulary rules (apply to every translation)

| English (CRM generic) | Church-appropriate translation |
|---|---|
| Members / Users / Clients | Congregation / Parishioners / Faithful |
| Leads / Prospects | Visitors / Seekers |
| Groups | Small Groups / Life Groups / Ministries |
| Giving / Payments / Transactions | Offerings / Tithes / Stewardship / Contributions |
| Pledge | Financial pledge / commitment to the church |
| Deposit (church finance) | Offering deposit |
| Cart (people selection) | Selection / List |
| Family | Family — keep as-is (core church membership unit) |

Standard UI terms (Save, Cancel, Delete, Edit, Search, etc.) → use the normal native-language UI translation.

Format specifiers (`%d`, `%s`, `%1$s`, `%2$d`, etc.) must be preserved **exactly** — never translate or remove them.

### Step 6 — Translate each batch file (one at a time)

For each batch file path from the `--info` output, process files **one at a time** to keep token usage low:

1. Load only the current file's untranslated terms:
```bash
node locale/scripts/locale-translate.js --read-file --file <batchFilePath>
```
This returns **only the untranslated entries** (empty string values or plural objects with empty forms) — already-translated keys are excluded.

2. Translate the returned terms into the target language, applying the denomination context and church vocabulary rules.
3. For plural forms (`{"one": "", "other": ""}`), produce grammatically correct singular and plural forms.
4. Build a JSON object of **only the keys you translated**.
5. **Write translations to a temp file using a file-write helper** (NEVER use bash heredoc or echo for non-ASCII content):

   Use your agent or CI's file-write helper to create `/tmp/<locale>-<n>.json` with the translation JSON, then apply:
   ```bash
   node locale/scripts/locale-translate.js --apply --file <batchFilePath> --translations "$(cat /tmp/<locale>-<n>.json)"
   ```

   **Why**: Bash heredocs and echo can mangle Unicode characters, typographic quotes, and apostrophes. Use a file-write helper (for example, an agent `Write` tool or a repository-provided writer) to reliably create non-ASCII JSON files.

6. Repeat for the next batch file.

### Step 6.5 — Commit after every group <!-- learned: 2026-03-06 -->

**MANDATORY: use `report_progress` after every group of locales is fully applied.** Never let more than one group accumulate without committing.

Group boundaries (commit after each):
1. All small locales (≤ 25 terms, 1 file) — commit with message `locale: translate small locales (af, sq, am, …)`
2. Medium single-file locales (26–200 terms, 1 file, e.g. it, th) — commit with message `locale: translate medium locales (it, th)`
3. Medium two-file locales (tr, sw, hu, ru, …) — commit with message `locale: translate medium two-file locales`
4. Large two-file locales (pt-br, pt, es-SV, …) — commit with message `locale: translate large locales`
5. Very large three-file locales (ro, et, fi, nb, sv, uk, vi) — commit with one message per locale or one message for the group

**Why commit often?**
- Protects work-in-progress from session failures
- Keeps diffs reviewable per language family
- Allows partial uploads to POEditor if the session is interrupted
- The translation batch files are the deliverable — committing them is how you save progress

In the main session use `report_progress`. In a background agent, stage and describe the files to apply in the main session so the main session can commit them.

### JSON Safety Rules (CRITICAL)

These rules prevent the most common translation failures:

**Rule 1 — Always use a file-write helper for JSON, never bash heredoc/echo**
```bash
# ❌ WRONG — breaks with Unicode, apostrophes, typographic quotes
node locale-translate.js --apply --file x.json --translations '{"Can'\''t scan?": "..."}'

# ✅ CORRECT — Use a file-write helper to create the file, then cat reads it
# (Create /tmp/<locale>-<n>.json with your agent's file-write helper first)
node locale-translate.js --apply --file x.json --translations "$(cat /tmp/locale-1.json)"
```

**Rule 2 — Avoid typographic/curly quotes inside translation values**

Some languages use typographic quote characters that look like JSON string delimiters. These can break JSON parsing if not handled correctly.

| Language | Problematic | Safe alternative |
|----------|-------------|-----------------|
| Chinese | `"链接"` (U+201C/U+201D) | `「链接」` (corner brackets) |
| Czech, German | `„Odkaz"` | Prefer non-ASCII-safe delimiters or ensure values are written via the file-write helper |
| General | Any `"` inside a value | Escape as `\"` or reword |

**Rule 3 — Validate JSON before applying (optional but recommended for large batches)**
```bash
python3 -c "import json; json.load(open('/tmp/<locale>-<n>.json')); print('OK')"
```

**Rule 4 — Apostrophes in shell strings**

If you must pass JSON directly in a bash argument (not recommended), escape apostrophes:
- `can't` → `can'\''t` inside single-quoted shell strings

### Step 7 — Write reviewer notice

After all translations are written, create or overwrite `locale/terms/missing/REVIEW_NOTES.md` with the following content (substituting real values for `<date>`, `<N>`, `<locales>`):

```markdown
# AI Translation Review — <date>

These translation files were generated by an AI agent on <date>.

**<N> locales translated:** <locales>

## For POEditor Reviewers

These are AI-generated translations and **must be reviewed by a native speaker** before they are accepted as final. Please check:

- [ ] Ecclesiastical vocabulary is appropriate for your denomination and region
- [ ] Format specifiers (`%d`, `%s`, etc.) are preserved exactly
- [ ] Plural forms are grammatically correct for your language
- [ ] UI terms (buttons, labels) sound natural and professional
- [ ] Any church-specific terms (offerings, congregation, ministries) match local usage

If a translation is incorrect, update it directly in POEditor. The AI suggestion is a starting point, not a final answer.

Thank you for reviewing!
```

### Step 8 — Report

After processing all files for a locale, print a brief summary:
```
✅ <Language> (<code>) — <N> terms translated across <M> file(s)
```

After all locales are done:
```
📊 Done — <N> locales, <T> total terms translated

📝 Review notice written to locale/terms/missing/REVIEW_NOTES.md
📤 Next steps:
   1. Upload locale/terms/missing/**/*.json to POEditor
   2. Share REVIEW_NOTES.md with your POEditor contributors so they know to review the AI translations
   3. After contributors approve/fix translations in POEditor, run: npm run locale:download
```

## Notes

- The `--apply` command merges translations into the batch file — already-translated terms are preserved.
- If a batch file has no untranslated terms, skip it silently.
- Work one locale at a time to keep context focused.
- If you are uncertain about a term's ecclesiastical meaning, prefer the most widely understood Christian term for the region over a narrow denominational one.

## Performance: Parallel Agents for `--all` mode

When translating many locales at once, sequential processing is slow. Use parallel background Task agents:

**Process order: smallest locales first, then scale up to larger ones.**

**Group locales by size** and launch one agent per group:
- **Small locales** (≤ 25 terms, 1 batch file): Do ALL of these in the main session first — fast and validate workflow
- **Medium locales** (26–200 terms, 1–2 batch files): Group 3–4 per background agent after smalls are done
- **Large locales** (200+ terms, 3+ batch files): Group 2–4 per background agent
- **Very large locales** (700+ terms, 6+ batch files): Dedicate 1 agent per locale, run last

**⚠️ Commit after EVERY group using `report_progress`.** Do not proceed to the next group until the current group is committed. This ensures progress is saved even if a later group fails.

**Background agents CANNOT use Bash** (they run without user interaction so Bash approval is never granted). Background agents must use only **Read** and **Write** tools to prepare translation files, then the main session runs the `--apply` Bash commands after agents complete.

**Agent prompt template (for background agents):**
```
Translate the following locales: <code1>, <code2>, ...

Working directory: /home/runner/work/CRM/CRM

For each locale:
1. Run: node locale/scripts/locale-translate.js --info --locale <code>
2. For each batch file, run: node locale/scripts/locale-translate.js --read-file --file <path>
3. Translate all terms. IMPORTANT: Use the `create` file-write tool to write translations to /tmp/<locale>-<n>.json (NEVER use bash heredoc/echo — it breaks Unicode)
4. Validate: python3 -c "import json; json.load(open('/tmp/<locale>-<n>.json')); print('OK')"
5. Apply: node locale/scripts/locale-translate.js --apply --file <path> --translations "$(cat /tmp/<locale>-<n>.json)"
6. After ALL locales in your group are applied, the MAIN SESSION will commit with report_progress.
```

**Main session commit flow for `--all` mode:**
```
Group 1 agents finish → main session verifies → report_progress → Group 2 agents start → …
```

Never start a new group of agents until the previous group is committed.

**Small locales share the same term keys** — all locales with exactly 13 terms share this key set:
`%d month old`, `%d year old`, `%d event in %s`, `person`, `%d Person added to the Cart.`,
`%d Person successfully added to selected Family.`, `%d Person successfully added to selected Group.`,
`%d person`, `%d hour`, `Are you sure you want to permanently delete this pledge record?`,
`Cancel and Return to Family View`, `If you delete this type, you will also remove all properties using it and lose any corresponding property assignments.`, `Move Donations to Selected Family`

This lets you pre-generate translations for multiple small locales simultaneously without reading batch files individually.
