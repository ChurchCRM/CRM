# Translate Missing ChurchCRM Locale Terms

You are translating missing UI terms for the ChurchCRM church management application across multiple locales.

## CRITICAL RULES — READ BEFORE DOING ANYTHING

1. **Create a BRAND-NEW branch FIRST** — before translating a single term. Never reuse an existing `locale/*` or `copilot/*` branch, even one from earlier today.
2. **After EVERY locale:** commit → push → upload to POEditor → commit refreshed files → push
3. **NEVER accumulate translations without committing** — sessions can timeout at any moment
4. **If any step fails, STOP and report** — do not continue without saving work

We have lost hours of translated work from agents that skipped these steps. They are non-negotiable. Reusing a stale branch also creates review-thread churn and can overwrite reviewer edits from the prior run.

---

## Step 1: Create a fresh Branch <!-- updated: 2026-04-22 -->

**Every session starts on a brand-new branch.** Append a UTC timestamp so the name is unique even when run twice the same day:

```bash
BRANCH="locales/7.2.0-$(date -u +%Y-%m-%d-%H%M%S)"
git checkout -b "$BRANCH"
git push -u origin "$BRANCH"
```

Preferred helper (auto-detects version and appends time suffix):

```bash
node locale/scripts/locale-branch-manager.js --init
# Output: locales/<version>-<YYYY-MM-DD>-<HHMMSS>
```

**Do not reuse the current branch even if it is already a `locale/*` branch.** If you are resuming after a timeout, still cut a new branch — prior sessions' completed locales are already pushed to their own branch and uploaded to POEditor, so nothing is lost.

---

## Step 2: List Remaining Locales

```bash
node locale/scripts/locale-translate.js --list
```

Process locales in this priority order:

- **TIER-1 (high impact):** es, es-MX, es-AR, es-CO, es-SV, zh-CN, zh-TW, pt-br, pt, hi, fr, ru, id, de, ja, ar
- **TIER-2 (medium):** sw, am, vi, te, it, ko, ta, th, uk, pl, nl, el, sv
- **TIER-3 (completeness):** ro, cs, hu, he, nb, fi, et, af, sq, fil, ml

Skip locales that show 0 terms remaining (already done).

---

## Step 3: For EACH Locale — Translate → Save → Upload

### 3a. Read the untranslated terms

```bash
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<CODE>/<CODE>-1.json
```

If the locale has multiple files (`<CODE>-2.json`, etc.), read and translate each file.

### 3b. Translate

Produce translations following these rules:

**Church vocabulary (use these, not generic CRM terms):**
| English | Translation concept |
|---------|-------------------|
| Members / Users | Congregation / Parishioners |
| Groups | Small Groups / Ministries |
| Giving / Payments | Offerings / Tithes / Contributions |
| Pledge | Financial pledge / commitment |
| Cart (selection) | Selection / Roster |

**Denomination context by locale:**
- **Coptic Orthodox:** ar (Egyptian Arabic)
- **Catholic:** es, es-*, it, fr, pt, pt-br, pl
- **Orthodox:** ru, uk, el, ro
- **Lutheran:** de, sv, nb, fi, et
- **Evangelical/Protestant:** ko, zh-CN, zh-TW, id, ja, vi, th
- **General Protestant:** en, af, sq, nl, hu, cs, he, sw, am, fil, ml, ta, te, hi

**Preserve exactly:** `%d`, `%s`, `%1$s` format specifiers, markdown, URLs, HTML tags

**Brand names — never translate:** ChurchCRM, Vonage, MailChimp, GitHub, OpenLP, Nextcloud, Gravatar, WebDAV, POEditor, ownCloud

**Leave as `""` (do NOT translate):** `N/A`, `name@example.com`, `SHA1 Hash`, `BCC`

**Plural forms:** For languages that require them (ar, ru, pl, cs, etc.), fill all required plural forms (`zero`, `one`, `two`, `few`, `many`, `other`).

### 3c. Apply translations

```bash
cat > /tmp/<CODE>-1-trans.json << 'ENDJSON'
{ ... your translations JSON ... }
ENDJSON

node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/<CODE>/<CODE>-1.json \
  --translations "$(cat /tmp/<CODE>-1-trans.json)"

rm /tmp/<CODE>-1-trans.json
```

Repeat for each batch file (`<CODE>-2.json`, etc.) if the locale has multiple.

### 3d. Update english-ok.json (if needed)

If any translations are intentionally identical to the English key (e.g. `"Kiosk": "Kiosk"`, `"Azure": "Azure"`), add them to `locale/terms/english-ok.json` so the uploader doesn't skip them:

```bash
# Check which terms have value = key
python3 -c "
import json
t = json.load(open('locale/terms/missing/<CODE>/<CODE>-1.json'))
same = [k for k, v in t.items() if isinstance(v, str) and v == k and v != '']
if same: print(json.dumps(same))
"
```

If any, add to `locale/terms/english-ok.json` under the locale code.

### 3e. COMMIT + PUSH (MANDATORY — do this IMMEDIATELY)

```bash
git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
git commit -m "locale: translate <CODE> (<LANGUAGE>, <N> terms)"
git push origin $(git branch --show-current)
```

### 3f. UPLOAD TO POEDITOR

```bash
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes
```

This uploads translations AND refreshes the local batch files (removes accepted terms).

The script reads `POEDITOR_TOKEN` from `.env`. Rate limits are handled automatically with retries.

**If the upload fails:** Log the error message and **skip to the next locale** (Step 3h). Do NOT retry or block. The upload can be re-run manually later. The committed+pushed translations are safe on the branch regardless.

### 3g. COMMIT REFRESHED FILES + PUSH (only if 3f succeeded)

If the upload succeeded and batch files were refreshed:

```bash
git add locale/terms/missing/<CODE>/
git commit -m "locale: update missing terms for <CODE> after POEditor upload"
git push origin $(git branch --show-current)
```

Skip this step if 3f failed (nothing changed locally).

### 3h. Move to the next locale. Go back to Step 3a.

---

## Parallelization Strategy

For speed, you may run up to 4 sub-agents in parallel for large locales (>10 terms each). Each sub-agent translates and applies ONE locale. After ALL sub-agents return, the parent MUST run steps 3e–3g for EACH locale before dispatching the next batch.

**Small locales (≤10 terms):** Handle inline — no sub-agent needed.
**Large locales (>10 terms):** One sub-agent per locale. Max 4 at a time.

---

## After All Locales Are Done

```bash
# Verify nothing remains
node locale/scripts/locale-translate.js --list

# Verify all uploads reached POEditor
node locale/scripts/poeditor-upload-missing.js --dry-run
```

---

## If Session Times Out <!-- updated: 2026-04-22 -->

All completed locales are safe (committed, pushed, uploaded to POEditor). To resume, **always cut a fresh branch** — never reuse the previous session's branch:

1. `node locale/scripts/locale-branch-manager.js --init`  (creates `locale/<version>-<YYYY-MM-DD>-<HHMMSS>`)
2. `node locale/scripts/locale-translate.js --list` to see what's left
3. Resume from the next untranslated locale

Prior branches stay as-is for review. Each run producing its own branch makes review + rollback trivial.

---

## Related Skills & Docs

- [`/locale-translate`](./locale-translate.md) — the canonical slash-command form of this workflow; use it when driving from Claude Code directly.
- [`/locale-release`](./locale-release.md) — release-time wrapper that invokes `/locale-translate`.
- [`locale-cloud-safe-translation.md`](../../.agents/skills/churchcrm/locale-cloud-safe-translation.md) — branch-manager internals and cloud-resume rationale.
- [`locale-stack-ranking.md`](../../.agents/skills/churchcrm/locale-stack-ranking.md) — **authoritative** TIER priorities (Step 2 above mirrors this).
- [`locale-ai-translation.md`](../../.agents/skills/churchcrm/locale-ai-translation.md) — **authoritative** church vocabulary + denomination context (Step 3b above mirrors this).
- [`i18n-localization.md`](../../.agents/skills/churchcrm/i18n-localization.md) — adding UI terms, `gettext`/`i18next.t` usage, what NOT to wrap.
