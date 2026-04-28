---
title: "Locale Translation Workflow"
intent: "Complete guide for translating missing UI strings across 40+ locales with cloud-safe durability and impact-based prioritization"
tags: ["i18n", "localization", "translation", "release"]
complexity: "intermediate"
---

# Locale Translation Workflow <!-- learned: 2026-04-01, updated: 2026-04-27 -->

This is the **authoritative, consolidated workflow** for translating ChurchCRM UI terms across all supported locales. It replaces and consolidates:
- `locale-ai-translation.md` (deprecated)
- `locale-cloud-safe-translation.md` (deprecated)
- `locale-workflow-simplified.md` (deprecated)
- `locale-stack-ranking.md` (deprecated)

---

## Overview

ChurchCRM translations follow a **three-phase process** with durability guarantees:

1. **Translate** — Claude translates missing terms for all locales using church-appropriate vocabulary
2. **Upload** — Validate & upload each locale to POEditor for human review
3. **Download** — GitHub Action automatically fetches approved translations

**Core principle:** Every translated locale is durably persisted in **three places** (local commit, remote GitHub branch, POEditor) before the next locale starts. This prevents data loss from cloud session timeouts.

---

## Quick Start

```bash
# 1. List locales with missing terms
/locale-translate --list

# 2. Translate all locales (entire workflow: translate → commit → push → upload)
/locale-translate --all

# 3. Wait for POEditor approval
# (your translation team reviews and translates)

# 4. GitHub Action automatically downloads and creates PR
# (no manual action needed)
```

---

## CRITICAL: Non-Negotiable Safety Rules

**Every translation session MUST follow these rules.** We have lost hours of work from agents that skipped these steps.

### Rule 1: Always create a fresh branch
```bash
# MANDATORY: Branch is created automatically by /locale-translate --init
# Never reuse a prior run's locale/* branch, even from earlier the same day
```
Branch format: `locale/{VERSION}-{YYYY-MM-DD}-{HHMMSS}` (e.g., `locale/7.2.0-2026-04-22-174530`)

**Why:** Unique timestamps prevent collisions when running multiple sessions per day.

### Rule 2: Commit after EVERY locale
```bash
# Translations are secure locally after commit
git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
git commit -m "locale: translate <code> (<language>, <N> terms)"
```

**Why:** If the machine crashes, at least local work is recoverable.

### Rule 3: Push after EVERY commit
```bash
# MANDATORY: Work is immediately on remote (cannot be lost to timeout)
git push origin $(git branch --show-current)
```

**Why:** Remote work survives cloud session timeouts.

### Rule 4: Upload to POEditor after EVERY push
```bash
# MANDATORY: Saves work to cloud
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes
```

**Why:** POEditor is the source of truth for what's been reviewed. Uploaded terms won't be retranslated if you resume.

### Rule 5: If cloud session times out, cut a BRAND-NEW branch
```bash
# Do NOT: git checkout the previous run's branch
# Do: Run /locale-translate --all again

/locale-translate --all
# → Automatically creates a fresh locale/{version}-{date}-{time} branch
# → Already-uploaded terms are skipped by POEditor
# → No duplicates
```

**Why:** Stale branches cause review-thread churn. POEditor deduplication handles everything automatically.

---

## Planning: Prioritize by Impact (Stack Ranking)

### Quick Reference

| Tier | Locales | Terms | Coverage | Time | When |
|------|---------|-------|----------|------|------|
| **TIER-1** | 11 | 1,553 | 53% world | 3h | MVP release |
| **TIER-2** | 15 | 2,681 | 80% world | +4h | Complete release |
| **TIER-3** | 11 | 1,542 | 83% + full EU | +2.5h | If time allows |
| **TIER-4** | 1 | 138 | 100% | +0.5h | Completionism |

**Total:** 38 locales, 5,914 terms, ~9.5 hours

### TIER-1: Highest Impact (3.25 hours, 11 locales)

**Group variant locales together — they share 90%+ vocabulary**

| Group | Locales | Terms | Time |
|-------|---------|-------|------|
| Spanish | es, es-MX, es-AR, es-CO, es-SV | 705 | 1h |
| Chinese | zh-CN, zh-TW | 279 | 45m |
| Portuguese | pt-BR, pt | 278 | 30m |
| Big singles | hi, fr, ru, id, de, ja, ar | 1,004 | 1.5h |

**Covers:** 3.8B speakers (53% of world)

### TIER-2: Regional Depth (4 hours, 15 locales)

```
sw  am  vi  te  it  ko  zh-TW  ta  th  es-CO  es-AR  uk  pl  nl  el
```

**Covers:** +1.3B speakers (80% total)
**Notes:** Telugu has 5 batch files (673 terms), Greek has 2 files (191 terms) — confirm all processed.

### TIER-3: Completeness (2.5 hours, 11 locales)

```
ro  sv  pt  cs  hu  he  af  sq  es-SV  nb  fi
```

**Covers:** +200M speakers (83% + full Europe)

### TIER-4: Completionism (0.5 hours, 1 locale)

```
et
```

---

## Phase 1: Translate Missing Terms

### Start Translation

```bash
# List locales with missing terms
/locale-translate --list

# Translate one locale
/locale-translate --locale fr

# Translate all locales (RECOMMENDED for bulk work)
/locale-translate --all
```

### Workflow for Each Locale (MANDATORY sequence)

1. **Claude reads** untranslated terms from `locale/terms/missing/{LOCALE}/{LOCALE}-N.json` batch files
2. **Apply church-appropriate vocabulary** (see Church Vocabulary table below)
3. **Commit immediately** — one commit per locale, never batched
4. **Push immediately** — work is on remote, safe from session timeout
5. **Upload to POEditor immediately** — `node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`

**Example commit message:**
```
locale: translate fr (French - France, 154 terms)
```

**Key principle:** Each locale gets commit + push + upload before the next locale starts. If interrupted mid-`--all`, all completed locales are safe on remote AND in POEditor.

### Church Vocabulary (Denomination-Aware)

Use these translations for core ChurchCRM terms:

| English | Translation Guidelines |
|---------|---|
| **Members/Users** | Congregation / Parishioners (varies by denomination) |
| **Groups** | Small Groups / Ministries / Cells |
| **Giving/Payments** | Offerings / Tithes / Contributions (region-specific) |
| **Pledge** | Financial pledge/commitment |
| **Cart (selection)** | Selection / Roster |
| **Family** | Family (keep as-is) |

**Denomination variants by locale:**
- **Catholic:** es, it, pt, pl, fr (emphasis on parish, flock)
- **Orthodox:** ru, uk, gr (church community)
- **Lutheran:** de, sv, no (congregation, community)
- **Evangelical:** ko, zh (local church, believers)
- **Other:** ja, ar, hi (consult native speakers if unsure)

### Do NOT Translate (Technical Terms)

Keep these as-is:
```
N/A, name@example.com, @, SMS, SMTP, API, HTTP, HTTPS, JSON, CSV, XML, HTML, CSS, URL, E.164, ICS, TLS, BCC, ChurchCRM, Vonage, MailChimp, OpenLP, GitHub, Gravatar, POEditor, MD5
```

### Parallel Sub-Agents (Fastest for 10+ locales)

**Tested:** 28 locales / 665 terms in ~30 minutes (April 2026)

1. **Small locales (≤10 terms):** Handle inline — all at once, one commit per locale
2. **Large locales (>10 terms):** Dispatch 4 parallel `general-purpose` sub-agents
3. **Each sub-agent:** Reads → translates → **applies before returning** (this step is critical)
4. **One locale per large-locale agent** (limits context pressure)
5. **MANDATORY after each agent completes:** commit → push → upload to POEditor

**Batch size:** Run 4 sub-agents in parallel max. More than 4 can cause context pressure.

**Special cases:**
- Telugu (te): 5 batch files (673 terms) — process separately
- Amharic (am): 100+ terms — process separately

---

## Phase 2: Upload to POEditor

### Per-Locale Upload

After each `--all` iteration completes, the upload happens automatically via the `/locale-translate` skill:

```bash
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes
```

### Catch-Up: Upload Missed Locales

If any uploads failed during translation, run a catch-up:

```bash
npm run locale:upload:missing -- --yes
```

### Upload Script Behavior

- Discovers all locale folders in `locale/terms/missing/`
- Validates each locale: checks for proper translations, suspects identical to key, empties
- Skips suspect and empty terms (keeps batch files clean)
- Uploads to POEditor with metadata (parsing & update counts)
- After successful upload, refreshes local missing-term files (removes accepted terms)

### Upload Flags

| Flag | Purpose |
|------|---------|
| `--locale es,fr,de` | Upload specific locales (comma-separated) |
| `--yes` / `-y` | Skip confirmation prompts (MANDATORY for agent use) |
| `--dry-run` | Show what would be uploaded without sending |

---

## Phase 3: Download (Automated)

After uploading all translations to POEditor:

1. **Notify your POEditor translation team** — they review and translate any Claude-provided drafts
2. **Wait for approval** — no manual action needed on your end
3. **GitHub Action runs automatically** (on schedule or manually triggered):
   - Fetches approved translations from POEditor
   - Generates/updates:
     - `src/locale/i18n/{locale}.json` — JSON translations
     - `src/locale/textdomain/{locale}/LC_MESSAGES/messages.{po,mo}` — gettext format
     - `locale/terms/missing/{locale}/` — batch files for next iteration
   - Creates a PR with all updates
4. **Review and merge the PR**

### Why This Three-Phase Approach Works

| Aspect | Benefit |
|--------|---------|
| **Single source of truth** | GH Action runs the download once; no redundant API calls |
| **Clearer git history** | Translation commits separate from download commits |
| **Easier debugging** | Each phase has its own script and logs |
| **Asynchronous approval** | Upload work happens immediately; download waits for human review |

---

## Example Workflow: 7.2.0 Release

```bash
# 1. Create a new branch (automatic via /locale-translate --init)
#    Branch: locale/7.2.0-2026-04-27-143015

# 2. Translate all missing terms
#    (each locale: translate → commit → push → upload)
/locale-translate --all
# → Per-locale commits: "locale: translate fr (French - France, 154 terms)"
# → Per-locale POEditor uploads: automatic via --yes
# → All work is on remote branch AND in POEditor

# 3. Verify all uploads reached POEditor (optional)
node locale/scripts/poeditor-upload-missing.js --dry-run

# 4. Share vocabulary context with POEditor team
#    (direct them to Church Vocabulary section above)

# 5. Wait for POEditor approval
#    (team reviews and translates any Claude drafts)

# 6. GitHub Action downloads (automatic, on schedule or manual trigger)
#    → Creates PR with src/locale/i18n/ updates

# 7. Review and merge PR
gh pr view
gh pr merge <pr-number>

# 8. (Optional) Clean up locale branch
git branch -D locale/7.2.0-2026-04-27-143015
git push origin --delete locale/7.2.0-2026-04-27-143015
```

---

## Data-Loss Prevention Matrix

| Scenario | Outcome |
|----------|---------|
| **Cloud timeout at locale 20 of 39** | First 20 locales are committed, pushed, and already in POEditor. Resume with a new branch — remaining locales continue; no duplicates. |
| **Local machine crash** | All completed work was pushed — nothing lost. |
| **Want to inspect a specific run** | `git log origin/locale/{version}-*` — each session's work is immutable on its own branch. |
| **Upload failure for one locale** | Translation is still committed + pushed. Retry upload with `npm run locale:upload:missing -- --locale <CODE>`. |

---

## Troubleshooting

**Q: How do I resume if interrupted during translation?**
A: Run `/locale-translate --all` again — it skips already-translated locales. It automatically creates a fresh branch; POEditor skips already-uploaded terms (no duplicates).

**Q: What if upload fails?**
A: Check POEditor API token in `.env`. Run `npm run locale:upload:missing` again (with or without `--yes`).

**Q: When will the download happen after my upload?**
A: The GH Action runs on schedule or can be triggered manually. It automatically downloads and creates a PR with the translated files.

**Q: Can I download manually while waiting?**
A: Yes: `npm run locale:download`. But the GH Action will do this anyway — you don't need to.

**Q: How do I update skill files on master while staying on a locale branch?**
A: Use `git worktree`:
```bash
# From your locale branch
git worktree add /tmp/crm-master master

# Edit files inside the worktree (use absolute paths)
# e.g. edit /tmp/crm-master/.agents/skills/churchcrm/some-skill.md

# Commit and push from the worktree
git -C /tmp/crm-master add .agents/skills/
git -C /tmp/crm-master commit -m "docs: update locale skill"
git -C /tmp/crm-master push origin master

# Remove the temp worktree
git worktree remove /tmp/crm-master
```
Your locale branch, working tree, and uncommitted changes remain unaffected.

---

## Related Skills & Documentation

- **`/locale-translate`** — Operational command reference (this file explains WHY; that file is the HOW)
- **`/locale-release`** — Full release-time pipeline that calls `/locale-translate`
- **`i18n-localization.md`** — Adding UI terms, `gettext`/`i18next.t` rules, what NOT to wrap
- **`git-workflow.md`** — Committing locale files before release
- **`.claude/commands/locale-translate.md`** — `/locale-translate` skill implementation

---

## Helper Scripts Reference

All locale operations use scripts under `locale/scripts/`:

| Script | Purpose | Driven by |
|--------|---------|-----------|
| `locale-translate.js` | Read/apply batch files | `/locale-translate` skill |
| `locale-branch-manager.js` | Create & manage `locale/*` branches | `/locale-translate` skill |
| `poeditor-upload-missing.js` | Validate & upload to POEditor | `npm run locale:upload:missing` |
| `poeditor-downloader.js` | Download from POEditor, generate formats | `npm run locale:download` (GH Action) |
| `locale-config.js` | Centralized configuration | All locale scripts |

---

Last consolidated: 2026-04-27
