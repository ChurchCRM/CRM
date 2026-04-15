---
title: "AI Locale Translation"
intent: "Translate missing terms using Copilot for speed or Claude Code for control"
tags: ["i18n","localization","translation","release"]
complexity: "intermediate"
---

# AI Locale Translation

Translate missing UI strings across locales with church-specific vocabulary.

---

## Quick Start

```bash
npm run locale:download
npm run locale:translate:list
```

**Then use Copilot (recommended) or Claude Code (`/locale-translate --all`)**

---

## ⚡ Parallel Sub-Agents (Fastest) <!-- learned: 2026-04-09 -->

**For 10+ locales:** Use the `/locale-translate` skill with parallel `general-purpose` sub-agents

1. **MANDATORY:** Create a `locale/*` branch FIRST (see `/locale-translate` Step 1)
2. **Small locales (≤10 terms):** Agent handles inline — all at once, one commit
3. **Large locales (>10 terms):** Agent dispatches 4 parallel `general-purpose` sub-agents
4. **Each sub-agent:** Reads → translates → applies (must apply before returning)
5. **MANDATORY after each locale:** commit → push → upload to POEditor (never skip)

**Tested result:** 28 locales / 665 terms in ~30 minutes (April 2026)

---

## Alternative: Manual locale-by-locale

For single locales or debugging:

```
/locale-translate --locale fr
```

---

## Church Vocabulary

| English | Translation |
|---------|------------|
| Members/Users | Congregation / Parishioners |
| Groups | Small Groups / Ministries |
| Giving/Payments | Offerings / Tithes / Contributions |
| Pledge | Financial pledge/commitment |
| Cart (selection) | Selection / Roster |
| Family | Family (keep as-is) |

**Denomination-aware by locale:** Coptic (ar), Catholic (es/it/pt/pl/fr), Orthodox (ru/uk/gr), Lutheran (de/sv/no), Evangelical (ko/zh), etc.

---

## Do Not Translate

N/A, name@example.com, @, SMS, SMTP, API, HTTP, HTTPS, JSON, CSV, XML, HTML, CSS, URL, E.164, ICS, TLS, BCC, ChurchCRM, Vonage, MailChimp, OpenLP, GitHub, Gravatar, POEditor, MD5

---

## ⛔ MANDATORY: Commit + Push + Upload After Each Locale <!-- learned: 2026-04-09 -->

```bash
# 1. Commit
git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
git commit -m "locale: translate <code> (<language>, <N> terms)"

# 2. Push (MANDATORY — saves work to remote)
git push origin $(git branch --show-current)

# 3. Upload to POEditor (MANDATORY — saves work to cloud)
node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes
```

**All three steps are MANDATORY after EVERY locale. No exceptions.**

- Never accumulate multiple locales without committing
- Never commit without pushing — local-only commits are lost if the machine/session dies
- Upload to POEditor (if fails → skip refreshed file commit, continue to next locale)
- After successful upload, commit the refreshed batch files (POEditor removes accepted terms)
- If push fails, stop and report. If upload fails, log error and continue (translations are safe on branch)

---

## Implementation Notes

- **Temp files for JSON:** Use `cat > /tmp/locale-trans.json` to avoid shell escaping
- **Spanish variants:** Share 95% vocab — translate `es` first, then others
- **Chinese variants:** Share 90% vocab — translate `zh-CN` first, then `zh-TW`
- **Telugu (te):** 5 batch files (673 terms) — confirm all processed
- **Greek (el):** 2 batch files (191 terms) — confirm both processed

---

## Stack-Rank Locales by Impact

See [Locale Stack Ranking](./locale-stack-ranking.md) for TIER-1 (53% coverage, 3hrs) → TIER-2 (80% coverage, +4hrs) → etc.

---

## Related

- [Locale Stack Ranking](./locale-stack-ranking.md) — Prioritize by impact
- [i18n & Localization](./i18n-localization.md) — Term consolidation, best practices
- [Git Workflow](./git-workflow.md) — committing locale files before release
- [Development Workflows](./development-workflows.md) — release checklist

---

## Performance Notes <!-- learned: 2026-04-04 -->

### Proven Strategy: Parallel Sub-Agents (April 2026 live run)

**Tested in production — April 4 2026: translated 28 locales / 665 terms in one session.**

**What worked:**

| Approach | Result |
|----------|--------|
| **Small locales (≤10 terms) inline** | ✅ Fastest — process all at once, one commit |
| **Large locales via parallel `general-purpose` sub-agents** | ✅ 4-6x throughput — 4 agents at a time, each gets own language |
| **`report_progress` tool for commit+push** | ✅ Only way that works — `--commit-and-push` script fails with 403 |

**What failed / pain points:**

| Problem | Fix |
|---------|-----|
| `locale-branch-manager.js --commit-and-push` → 403 on push | Use `report_progress` tool instead |
| `locale-branch-manager.js --init` → 403 on push (but branch created OK) | Ignore the error, branch exists locally |
| Sub-agent that generates translations but doesn't apply → lost work | Sub-agent prompt must include the `--apply` step and verify output before returning |
| Sub-agent timeout before apply completes | Keep sub-agent prompts focused; one locale per agent for locales >100 terms |

### Optimal Batch Size

- **Run 4 sub-agents in parallel** — more than 4 can cause context pressure
- **Telugu (te) and Amharic (am)** — do separately (>100 terms each)
- **Variant groups (es/es-MX/es-AR...)** — process in one agent (shared vocab, trivial diff)

### Commit Strategy (MANDATORY — no exceptions) <!-- learned: 2026-04-09 -->

1. **All small locales** (≤10 terms) → one commit + push + POEditor upload per locale (or batch of ≤3)
2. **Each large locale** (>10 terms) → one commit + push + POEditor upload after sub-agent confirms apply
3. **NEVER proceed to next locale without:** commit → push (REQUIRED), upload (continue even if fails)
4. **If session may timeout:** commit+push more frequently, not less. Upload failures don't block progress.

---

Last updated: April 2026
