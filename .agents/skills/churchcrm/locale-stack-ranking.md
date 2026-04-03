---
title: "Locale Translation Stack Ranking"
intent: "Prioritize translations by impact for incremental rollout"
tags: ["i18n","localization","prioritization"]
complexity: "beginner"
---

# Locale Translation Stack Ranking

Translate by impact, not alphabetically. This maximizes user coverage if rolling out over time.

---

## Quick Reference

| Tier | Locales | Terms | Coverage | Time | When |
|------|---------|-------|----------|------|------|
| **TIER-1** | 11 | 1,553 | 53% | 3h | MVP release |
| **TIER-2** | 15 | 2,681 | 80% | +4h | Complete release |
| **TIER-3** | 11 | 1,542 | 83%+EU | +2.5h | If time allows |
| **TIER-4** | 1 | 138 | 100% | +0.5h | Completionism |

---

## TIER-1: Highest Impact (3 hours, 11 locales)

```
zh-CN  es  hi  fr  ru  pt-br  id  es-MX  de  ja  ar
```

**Covers:** 3.8B speakers (53% of world)  
**Batch:** All 1,553 terms in one Copilot session

---

## TIER-2: Regional Depth (4 hours, 15 locales)

```
sw  am  vi  te  it  ko  zh-TW  ta  th  es-CO  es-AR  uk  pl  nl  el
```

**Covers:** +1.3B speakers (80% total)  
**Note:** Telugu has 5 batch files (673 terms), Greek has 2 files (191 terms)

---

## TIER-3: Completeness (2.5 hours, 11 locales)

```
ro  sv  pt  cs  hu  he  af  sq  es-SV  nb  fi
```

**Covers:** +200M speakers (83% + full Europe)

---

## TIER-4: Completionism (0.5 hours, 1 locale)

```
et
```

---

## Implementation

1. **Use Copilot (Haiku)** — batch entire tier together, 2-3x faster than Claude Code agents
2. **Spanish variants:** Translate `es` first, then `es-MX`, `es-AR`, `es-CO`, `es-SV` (95% shared vocab)
3. **Chinese variants:** Translate `zh-CN` first, then `zh-TW` (90% shared vocab)
4. **Telugu (te):** 5 batch files — confirm all processed
5. **Greek (el):** 2 batch files — confirm both processed

---

## Related

- [AI Locale Translation](./locale-ai-translation.md) — Workflow, church vocabulary, Copilot recommendation
