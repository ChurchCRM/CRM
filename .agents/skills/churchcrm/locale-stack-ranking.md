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

## TIER-1: Highest Impact (3.25 hours, 11 locales)

**Group variant locales together — they share 90%+ vocabulary**

| Group | Locales | Terms | Time |
|-------|---------|-------|------|
| Spanish | es, es-MX, es-AR, es-CO, es-SV | 705 | 1h |
| Chinese | zh-CN, zh-TW | 279 | 45m |
| Portuguese | pt-br, pt | 278 | 30m |
| Big singles | hi, fr, ru, id, de, ja, ar | 1,004 | 1.5h |

**Covers:** 3.8B speakers (53% of world)  
**How:** Translate each group in separate Haiku session, commit each locale atomically

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

1. **Use Haiku agent** — process variant groups together (tested: 705 Spanish terms in 1h, 147 French in 15m)
2. **Variant groups:** One agent session per language family (Spanish bloc, Chinese bloc, etc.) → consistent vocabulary
3. **Commit per locale:** Each locale gets separate commit (atomic history)
4. **Multi-file locales:**
   - Telugu (te): 5 batch files (673 terms) — confirm all 5 processed
   - Greek (el): 2 batch files (191 terms) — confirm both processed

---

## Related

- [AI Locale Translation](./locale-ai-translation.md) — Workflow, church vocabulary, Copilot recommendation
