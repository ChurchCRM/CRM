# Translate Missing ChurchCRM Locale Terms

You are translating missing UI terms for the ChurchCRM church management application across multiple locales.

## CRITICAL RULES — READ BEFORE DOING ANYTHING

1. **PRE-FLIGHT FIRST** — Check GitHub for existing locale branches before creating a new one (see Pre-flight below).
2. **Create a BRAND-NEW branch** — before translating a single term. Never reuse an existing `locale/*` or `copilot/*` branch, even one from earlier today.
3. **Commit + push after EVERY group (5 locales max)** — never accumulate more than 5 locales without committing. Prefer committing after every 3.
4. **This session ends in ~55 minutes** — after 45 minutes, stop translating and commit everything you have. Partial progress is better than lost work.
5. **NO POEditor upload** — `POEDITOR_TOKEN` is not available in this environment. Skip all upload steps entirely. The user will run the upload manually after the session.
6. **If any step fails, STOP and report** — do not continue without saving work.

We have lost hours of work from agents that accumulated 10+ locales without committing.

---

## PRE-FLIGHT: Check What's Already Done <!-- added: 2026-04-25 -->

**Before creating a branch or translating anything**, check if prior sessions already completed some locales:

```bash
# 1. Check for existing locale branches from today
git ls-remote --heads origin 'refs/heads/locale/*'

# 2. Check for any open locale translation PRs
gh pr list --state open --search "locale translate" --json number,title,headRefName

# 3. Check what terms actually remain
node locale/scripts/locale-translate.js --list
```

If a prior session branch exists with commits for some locales, those locales will already show 0 terms remaining in `--list`. **Do not re-translate locales that show 0 terms.**

---

## Step 1: Create a Fresh Branch

```bash
node locale/scripts/locale-branch-manager.js --init
# Output: locale/<version>-<YYYY-MM-DD>-<HHMMSS>
```

If that fails:
```bash
git checkout -b "locale/$(node -p "require('./package.json').version")-$(date -u +%Y-%m-%d-%H%M%S)"
git push -u origin HEAD
```

---

## Step 2: Get Remaining Locales

```bash
node locale/scripts/locale-translate.js --list
```

Process locales in this order — **groups of ~5 locales, commit+push after each group:**

### Group A — Latin America & Iberia (Romance, easiest, highest GA traffic)
`es`, `es-MX`, `pt-br`, `pt`, `es-AR`

### Group B — More Romance + Catholic Europe
`es-CO`, `es-SV`, `fr`, `it`, `ro`

### Group C — Eastern Europe (Slavic, Orthodox/Catholic majority)
`ru`, `uk`, `pl`, `cs`, `hu`

### Group D — Northern Europe (Lutheran, Protestant)
`de`, `nl`, `sv`, `nb`, `fi`, `et`

### Group E — Asia Pacific (Philippines first — 91% Christian, then Korea, Indonesia, Vietnam, China)
`fil`, `ko`, `id`, `vi`, `zh-CN`

### Group F — More Asia
`zh-TW`, `hi`, `ja`, `ta`, `te`

### Group G — Africa & other (South Africa region, East Africa)
`sw`, `am`, `af`, `sq`, `ml`

### Group H — Completion (harder scripts)
`ar`, `el`, `he`, `th`

Skip locales that show 0 terms remaining.

---

## Step 3: For EACH Locale — Translate → Apply → Commit → Push

### ⏱️ TIMING CHECKPOINTS
- After every group (max 5 locales): commit + push before starting next group
- If 45 minutes have elapsed: finish current locale, commit everything, stop and report

### 3a. Read the untranslated terms

```bash
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<CODE>/<CODE>-1.json
```

If the locale has multiple files (`<CODE>-2.json`, etc.), read and translate each file.

### 3b. Translate

**Church vocabulary (use these, not generic CRM terms):**

| English | Translation concept |
|---------|-------------------|
| Members / Users | Congregation / Parishioners |
| Groups | Small Groups / Ministries |
| Giving / Payments | Offerings / Tithes / Contributions |
| Pledge | Financial pledge / commitment |
| Cart (selection) | Selection / Roster |

**Denomination context by locale:**
- **Catholic:** es, es-*, it, fr, pt, pt-br, pl, ro
- **Orthodox:** ru, uk, el
- **Coptic Orthodox:** ar
- **Lutheran:** de, sv, nb, fi, et
- **Evangelical/Protestant:** ko, zh-CN, zh-TW, id, ja, vi, th, fil
- **General Protestant:** af, sq, nl, hu, cs, he, sw, am, ml, ta, te, hi

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

If any translations are intentionally identical to the English key (e.g. `"Kiosk": "Kiosk"`, `"Email": "Email"`), add them to `locale/terms/english-ok.json` so the uploader doesn't skip them.

### 3e. COMMIT + PUSH (MANDATORY — after every locale, or at most every 3)

```bash
git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
git commit -m "locale: translate <CODE> (<LANGUAGE>, <N> terms)"
git push origin $(git branch --show-current)
```

Verify push succeeded:
```bash
git log origin/$(git branch --show-current) --oneline | head -3
```

**⛔ NO POEditor upload step.** Skip it entirely — `POEDITOR_TOKEN` is not available. The upload will be run manually after the session ends.

### 3f. Move to the next locale. Go back to Step 3a.

---

## Parallelization Strategy

For speed, run up to 4 sub-agents in parallel within a group. Each sub-agent translates and applies ONE locale. After ALL sub-agents in a group return, the parent MUST commit+push for each locale before starting the next group.

**Small locales (≤10 terms):** Handle inline — no sub-agent needed.
**Large locales (>10 terms):** One sub-agent per locale. Max 4 parallel.

---

## After All Locales Are Done

```bash
# Verify nothing remains
node locale/scripts/locale-translate.js --list
```

Report which locales were completed. The user will then run the POEditor upload manually:
```bash
for locale in <completed locales>; do
  node locale/scripts/poeditor-upload-missing.js --locale $locale --yes
done
```

---

## If Session is Approaching Timeout (45+ minutes elapsed)

1. Finish the locale you are currently on
2. Commit + push everything
3. Run `node locale/scripts/locale-translate.js --list` and report what remains
4. Do NOT start another locale if less than 5 minutes remain

---

## Related Skills & Docs

- [`/locale-translate`](./locale-translate.md) — the canonical slash-command form of this workflow.
- [`locale-cloud-safe-translation.md`](../../.agents/skills/churchcrm/locale-cloud-safe-translation.md) — branch-manager internals and cloud-resume rationale.
- [`locale-stack-ranking.md`](../../.agents/skills/churchcrm/locale-stack-ranking.md) — original TIER priorities.
- [`locale-ai-translation.md`](../../.agents/skills/churchcrm/locale-ai-translation.md) — church vocabulary + denomination context.
