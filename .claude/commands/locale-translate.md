# /locale-translate

Translate missing ChurchCRM UI terms for one or all locales.

**Arguments:** `--list`, `--locale <code>`, or `--all`

---

## ⛔ MANDATORY: Data Loss Prevention Rules <!-- learned: 2026-04-09, updated: 2026-04-22 -->

**These rules are NON-NEGOTIABLE. Every translation session MUST follow them.**

1. **ALWAYS create a BRAND-NEW branch** before translating ANY locale — never reuse an existing `locale/*` or `copilot/*` branch, even from earlier the same day
2. **ALWAYS commit + push after EVERY locale** — never accumulate uncommitted translations
3. **ALWAYS upload to POEditor after EVERY locale** — reduces manual steps
4. **If any step fails, STOP and report** — do not continue without saving work

**Why:** Cloud/remote agent sessions can timeout at any moment. Uncommitted translations are LOST FOREVER. We have lost hours of work from agents that translated 20+ locales without committing. Reusing old branches also causes review-thread churn and can silently overwrite reviewer edits from the prior run.

---

## Step 1: Branch setup (MANDATORY) <!-- learned: 2026-04-09, updated: 2026-04-22 -->

**MUST happen before any translation work begins.**

**Every session starts on a brand-new branch.** Never resume work on a `locale/*` or `copilot/*` branch from a previous run — always cut a fresh one. The branch manager appends a `HHMMSS` UTC timestamp so branches are unique even when run multiple times the same day.

```bash
node locale/scripts/locale-branch-manager.js --init
# Output: locale/<VERSION>-<YYYY-MM-DD>-<HHMMSS>  (e.g. locale/7.2.0-2026-04-22-174530)
# If it errors on push, ignore the error — branch was created locally
```

If the branch manager fails entirely, create manually (include the time suffix):
```bash
git checkout -b "locale/$(node -p "require('./package.json').version")-$(date -u +%Y-%m-%d-%H%M%S)"
```

**Do not reuse the current branch even if it looks like a locale branch.** If you are already on a `locale/*` branch from an earlier session, still run `--init` to cut a fresh one.

**Do NOT proceed to Step 2 until you are on a brand-new `locale/*` branch created in this session.**

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

### 4d. Commit and push IMMEDIATELY (MANDATORY) <!-- learned: 2026-04-09 -->

**⛔ NEVER skip this step. NEVER accumulate multiple locales without committing.**

After each locale (or small batch of ≤3 trivial locales), commit and push:

**Option A — `report_progress` tool** (GitHub Copilot / remote agents):
Use the `report_progress` tool which runs `git add . && git commit && git push`.

**Option B — direct git** (Claude Code / local agents):
```bash
git add locale/terms/missing/<LOCALE>/ locale/terms/english-ok.json
git commit -m "locale: translate <CODE> (<LANGUAGE>, <N> terms)"
git push origin $(git branch --show-current)
```

**Option C — branch manager script**:
```bash
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale <CODE> --language "<LANGUAGE>" --terms <N>
```

**If push fails with 403:** Try `report_progress` instead. If that also fails, at minimum `git commit` locally so work is not lost, then report the push failure.

### 4e. Upload to POEditor IMMEDIATELY (MANDATORY) <!-- learned: 2026-04-09 -->

**⛔ After EVERY locale is committed, upload it to POEditor right away.**

```bash
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes
```

- `--yes` skips confirmation prompts (agent should not wait for human input)
- The script automatically refreshes local missing-term files after upload (removes translated terms from batch files)
- The script reads `POEDITOR_TOKEN` from `.env` automatically
- Rate limit: POEditor allows 1 upload per 20s — the script handles retries

### 4f. Commit the refreshed batch files (only if 4e succeeded) <!-- learned: 2026-04-09 -->

If the upload succeeded and the batch files were refreshed by POEditor, commit the updated files:

```bash
git add locale/terms/missing/<CODE>/
git commit -m "locale: update missing terms for <CODE> after POEditor upload"
git push origin $(git branch --show-current)
```

This keeps the branch in sync with POEditor's state — the next agent session (or resume) sees accurate remaining work.

**Skip this step if upload failed** — nothing changed locally, nothing to commit.

**If upload fails:** Log the error, **skip step 4f**, and continue to the next locale. The upload can be retried later with `npm run locale:upload:missing -- --locale <CODE>`. The committed+pushed translations are safe on the branch regardless.

**Why upload immediately?** If the agent times out, all committed+uploaded locales are already in POEditor. Without this step, someone must manually run the upload for all translated locales.

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

**After sub-agent returns:** The parent agent MUST immediately:
1. `git add locale/terms/missing/<CODE>/` + `git commit` + `git push` (or `report_progress`)
2. `node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`
3. Only THEN proceed to the next locale

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

## Resume after timeout <!-- updated: 2026-04-22 -->

**A resumed session is a new session.** Always cut a fresh branch with `node locale/scripts/locale-branch-manager.js --init` — do NOT `git checkout` the prior run's `locale/*` branch. Because every completed locale is already pushed + uploaded to POEditor, starting clean loses nothing.

Run `node locale/scripts/locale-translate.js --list` on the new branch to see which locales still have terms. Already-translated locales show 0 or 1 remaining (the 1 is usually `N/A`). Resume from there.

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

---

## Related Skills & Docs

- [`/locale-release`](./locale-release.md) — release-time wrapper: regenerates missing terms, invokes this command, then downloads approved translations.
- [`/locale-translate-agent-prompt`](./locale-translate-agent-prompt.md) — copy-paste prompt template for Copilot / remote agents (same workflow, different framing).
- [`locale-cloud-safe-translation.md`](../../.agents/skills/churchcrm/locale-cloud-safe-translation.md) — branch-manager internals, branch naming (`locale/{v}-{YYYY-MM-DD}-{HHMMSS}`), cloud-resume mechanics.
- [`locale-stack-ranking.md`](../../.agents/skills/churchcrm/locale-stack-ranking.md) — **authoritative** TIER-1/2/3 prioritization (the list in Step 5 above mirrors this).
- [`locale-ai-translation.md`](../../.agents/skills/churchcrm/locale-ai-translation.md) — **authoritative** church vocabulary / denomination context (the summary in Step 4b above mirrors this).
- [`i18n-localization.md`](../../.agents/skills/churchcrm/i18n-localization.md) — adding UI terms, `gettext`/`i18next.t` usage, and what NOT to wrap (brand/technical literals).
