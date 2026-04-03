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

## ⚡ Recommended: Use Copilot (Faster)

**For 10+ locales:** Use Copilot with Haiku → 2-3x faster than Claude Code agents

1. `npm run locale:download` (generates missing-terms batches)
2. Open Copilot chat, copy entire Copilot translation prompt, paste all locales needing translation
3. Copilot batch-processes all locales
4. Copy results back, verify with `npm run locale:audit`
5. Upload: `npm run locale:upload:missing`

---

## Alternative: Use Claude Code

For single locales or when you prefer control:

```
/locale-translate --all
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

## Commit After Each Locale

```bash
git commit -m "locale: translate <code> (<language>, <N> terms)"
```

Commit immediately after translating each locale. Do not batch multiple locales.

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

## Performance Notes <!-- learned: 2026-04-02 -->

### Model Selection for Bulk Translation

**Tested approaches (April 2026):**
- **Claude Code (Opus)** with sequential agents: ⚠️ Slow — agents hit permission barriers, timeouts common
- **Copilot (Haiku/Fast mode)**: ✅ **RECOMMENDED** — 2-3x faster for 36+ locale runs, parallel processing works better
- **Manual batch scripts**: ⚠️ Slow — requires many Context windows to generate all translations

**Decision:** For future bulk translations, route through Copilot instead of Claude Code agents. Copilot is optimized for high-volume parallel work.

---

Last updated: April 2026
