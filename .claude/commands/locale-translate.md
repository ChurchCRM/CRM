# /locale-translate

Translate missing ChurchCRM UI terms for one or all locales.

**Arguments:** `--list`, `--locale <code>`, or `--all`

---

## Step 1: Branch setup <!-- learned: 2026-04-04 -->

Check if already on a `locale/*` branch first:

```bash
git branch --show-current
```

If already on a `locale/*` branch, **skip to Step 2**.

If not, try the branch manager (it may fail on push with 403 — that's OK, the branch is created locally):

```bash
node locale/scripts/locale-branch-manager.js --init
# If it errors on push, ignore the error — branch was created
```

The push will be handled by `report_progress` below.

---

## Step 2: Get remaining locales

```bash
node locale/scripts/locale-translate.js --list
```

---

## Step 3: Strategy — small vs. large locales <!-- learned: 2026-04-04 -->

**Split the work based on term count:**

- **Small locales (≤ 10 terms):** Process ALL of them directly in one pass. Read each file, produce translations inline, apply with temp files. Batch the `report_progress` commit at the end covering all small locales.
- **Large locales (> 10 terms):** Dispatch parallel `general-purpose` sub-agents — one per locale (or small groups of 2). Each sub-agent reads, translates, AND applies before returning. Then commit with `report_progress`.

**Why this split works:**
- Small locales are often just `N/A`, `BCC`, `name@example.com` — trivial to handle inline
- Large locales (60+ terms) each benefit from a dedicated sub-agent with full language context
- Parallel sub-agents for large locales = 4-6x throughput

---

## Step 4: For EACH locale with remaining terms

### 4a. Read the untranslated terms

```bash
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<LOCALE>/<LOCALE>-1.json
```

Every key with `""` value needs a translation.

### 4b. Translation rules

- **Church vocabulary:** Members→Congregation, Groups→Ministries, Giving→Offerings, Pledge→Financial commitment, Cart→Selection
- **Denomination context:** Catholic (es/it/fr/pt/pl), Orthodox (ru/uk/el/ro), Lutheran (de/sv/nb/fi/et), Evangelical (ko/zh-CN/zh-TW/id), Coptic (ar)
- **Preserve exactly:** `%d`, `%s`, `%1$s` format specifiers, markdown formatting, URLs
- **Brand names — never translate:** ChurchCRM, Vonage, MailChimp, GitHub, OpenLP, Nextcloud, Gravatar, WebDAV, POEditor, ownCloud
- **Leave as `""` (do NOT translate):** `N/A`, `name@example.com`, `SHA1 Hash`, `BCC`

### 4c. Apply via temp file

```bash
cat > /tmp/<LOCALE>-1-trans.json << 'ENDJSON'
{ ... your translations JSON ... }
ENDJSON

node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/<LOCALE>/<LOCALE>-1.json \
  --translations "$(cat /tmp/<LOCALE>-1-trans.json)"

rm /tmp/<LOCALE>-1-trans.json
```

For locales with multiple batch files (Telugu has 2 files), repeat for each file.

### 4d. Commit and push using report_progress tool <!-- learned: 2026-04-04 -->

**IMPORTANT: Do NOT use `locale-branch-manager.js --commit-and-push`** — it requires direct git push which fails with 403.

Instead, use the `report_progress` tool after each locale (or batch of small locales). The tool runs `git add . && git commit && git push` using the agent's credentials.

---

## Step 5: Repeat for next locale

Priority order (highest impact first):

**TIER-1 (53% world coverage):** es, es-MX, es-AR, es-CO, es-SV, zh-CN, zh-TW, pt-br, pt, hi, fr, ru, id, de, ja, ar
**TIER-2 (80% coverage):** sw, am, vi, te, it, ko, ta, th, uk, pl, nl, el, sv
**TIER-3 (completeness):** ro, cs, hu, he, nb, fi, et, af, sq

---

## Sub-agent prompt template for large locales <!-- learned: 2026-04-04 -->

Use this exact structure when dispatching `general-purpose` sub-agents for locales with 10+ terms:

```
You are translating missing ChurchCRM UI terms into <LANGUAGE> (<CODE>) for a <DENOMINATION> church management application.

Working directory: /home/runner/work/CRM/CRM

Read the missing terms file:
  node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<CODE>/<CODE>-1.json

Produce high-quality <LANGUAGE> translations for ALL terms (<N> terms). Rules:
- Denomination context: <DENOMINATION> (<specifics>)
- Church vocabulary: Members→<local>, Groups→<local>, Giving→<local>, Pledge→<local>
- Preserve %d, %s, %1$s format specifiers exactly
- Preserve markdown formatting, URLs, and brand names (ChurchCRM, Vonage, MailChimp, GitHub, OpenLP, Nextcloud, etc.)
- Leave these keys with "" value (do NOT translate): N/A, name@example.com, SHA1 Hash, BCC
- For long markdown documentation strings - translate fully into <LANGUAGE>

After producing translations, apply them:
1. Write translations to /tmp/<CODE>-1-trans.json using a cat heredoc
2. Run: node locale/scripts/locale-translate.js --apply --file locale/terms/missing/<CODE>/<CODE>-1.json --translations "$(cat /tmp/<CODE>-1-trans.json)"
3. Remove /tmp/<CODE>-1-trans.json

Return: "✅ Applied N translations to locale/terms/missing/<CODE>/<CODE>-1.json"
```

**Critical:** The sub-agent MUST apply before returning. If it only produces translations without applying, the work is lost.

---

## Example: Translating French (small locale)

```bash
# Read terms
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/fr/fr-1.json
# Output: {"Confession": "", "Format": "", ...}

cat > /tmp/fr-1-trans.json << 'ENDJSON'
{"Confession": "Confession", "Format": "Format", "Minimum": "Minimum", "Options": "Options", "Photo": "Photo", "Communication": "Communication", "N/A": "", "Parents": "Parents", "Configuration": "Configuration", "Continent": "Continent", "options": "options", "page": "page"}
ENDJSON

node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/fr/fr-1.json \
  --translations "$(cat /tmp/fr-1-trans.json)"

rm /tmp/fr-1-trans.json

# Use report_progress to commit+push (not locale-branch-manager)
```

---

## Resume after timeout

Run `node locale/scripts/locale-translate.js --list` to see which locales still have terms. Already-translated locales show 0 or 1 remaining (the 1 is usually `N/A`). Resume from there.

---

## Verification

After all locales are done:

```bash
node locale/scripts/locale-translate.js --list
# Should show "Total: 0 locales, 0 terms" or only N/A entries
```

---

## English-OK allowlist <!-- learned: 2026-04-08 -->

Some terms have `value = key` intentionally — country names, brand names, and universal tech terms
that stay in English regardless of locale (e.g. `"Australia": "Australia"` in Filipino).

The upload script (`poeditor-upload-missing.js`) normally skips these as "suspect" (identical to source key).
To mark them as safe to upload, add them to `locale/terms/english-ok.json`:

```json
{
  "fil": ["Australia", "Admin", "Dashboard", "Email/Username", ...],
  "id": ["Australia", ...]
}
```

When translating a locale with country names (e.g. fil), set `value = key` for countries and then
add ALL those country/tech/brand terms to the `english-ok.json` allowlist so the uploader treats
them as valid translations, not untranslated suspects.

To review which terms would be allowlisted for a locale:
```bash
python3 -c "
import json
d = json.load(open('locale/terms/english-ok.json'))
print(f\"fil: {len(d.get('fil', []))} terms\")
"
```
