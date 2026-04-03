---
title: "Locale Translation Stack Ranking"
intent: "Prioritize locale translations by impact and value to maximize coverage when translating incrementally"
tags: ["i18n","localization","prioritization","strategy"]
complexity: "beginner"
---

# Locale Translation Stack Ranking

When translating locales incrementally (rather than all at once), prioritize by **speaker population and regional reach** to maximize impact per translation effort.

---

## Summary by Tier

| Tier | Count | Terms | Focus | When to translate |
|------|-------|-------|-------|-------------------|
| **TIER-1** | 11 locales | 1,553 | Highest speaker pop | **First** — covers 3.8B+ speakers |
| **TIER-2** | 15 locales | 2,681 | High impact | **Second** — adds regional depth |
| **TIER-3** | 11 locales | 1,542 | Niche markets | **Third** — completeness |
| **TIER-4** | 1 locale | 138 | Ultra-niche | **Last** — if time allows |

---

## TIER-1: Translate First (1,553 terms, 11 locales)

**Strategy:** These 11 locales cover 3.8+ billion speakers. Prioritize by speaker population.

| Rank | Locale | Language | Terms | Speakers | Notes |
|------|--------|----------|-------|----------|-------|
| 1 | `zh-CN` | Chinese (Simplified) | 139 | 1.1B | Largest single language |
| 2 | `es` | Spanish (Spain) | 141 | 500M | Base Spanish + 4 regional variants |
| 3 | `hi` | Hindi | 140 | 345M | India's primary tech language |
| 4 | `fr` | French | 147 | 280M | Africa + Europe + diaspora |
| 5 | `ru` | Russian | 139 | 260M | Eastern Europe + diaspora |
| 6 | `pt-br` | Portuguese (Brazil) | 137 | 220M | South America's largest market |
| 7 | `id` | Indonesian | 145 | 200M | Southeast Asia |
| 8 | `es-MX` | Spanish (Mexico) | 141 | 130M | Americas variant of Spanish |
| 9 | `de` | German | 143 | 130M | Central Europe |
| 10 | `ja` | Japanese | 141 | 125M | East Asia tech market |
| 11 | `ar` | Arabic (Egypt) | 140 | 102M | MENA region base |

**Effort:** ~3 hours with Copilot (all 1,553 terms in one batch)  
**ROI:** Covers 53% of Earth's population

---

## TIER-2: Translate Second (2,681 terms, 15 locales)

**Strategy:** Regional variants, India/Africa/Asia expansion, and specialized markets.

| Rank | Locale | Language | Terms | Speakers | Notes |
|------|--------|----------|-------|----------|-------|
| 12 | `sw` | Swahili | 140 | 150M | East Africa (5 countries) |
| 13 | `am` | Amharic | 139 | 120M | Ethiopia (diaspora + local) |
| 14 | `vi` | Vietnamese | 142 | 95M | Southeast Asia |
| 15 | `te` | Telugu | 673 | 95M | ⚠️ 5 batch files — largest single locale |
| 16 | `it` | Italian | 137 | 85M | Southern Europe |
| 17 | `ko` | Korean | 141 | 81M | East Asia |
| 18 | `zh-TW` | Chinese (Traditional) | 140 | 80M | Taiwan + diaspora |
| 19 | `ta` | Tamil | 137 | 80M | India/Sri Lanka |
| 20 | `th` | Thai | 139 | 70M | Southeast Asia |
| 21 | `es-CO` | Spanish (Colombia) | 141 | 50M | Americas variant |
| 22 | `es-AR` | Spanish (Argentina) | 141 | 46M | Americas variant |
| 23 | `uk` | Ukrainian | 141 | 40M | Eastern Europe |
| 24 | `pl` | Polish | 142 | 38M | Central Europe |
| 25 | `nl` | Dutch | 137 | 25M | Benelux |
| 26 | `el` | Greek | 191 | 13M | ⚠️ 2 batch files |

**Effort:** ~4 hours with Copilot (all 2,681 terms)  
**ROI:** Adds 27% more population; covers 80% of Earth

---

## TIER-3: Translate for Completeness (1,542 terms, 11 locales)

**Strategy:** European + niche markets. Include if you want full language coverage.

| Rank | Locale | Language | Terms | Speakers | Notes |
|------|--------|----------|-------|----------|-------|
| 27 | `ro` | Romanian | 143 | 19M | Eastern Europe |
| 28 | `sv` | Swedish | 140 | 13M | Scandinavia |
| 29 | `pt` | Portuguese (Portugal) | 141 | 10M | Europe variant |
| 30 | `cs` | Czech | 139 | 10M | Central Europe |
| 31 | `hu` | Hungarian | 138 | 10M | Central Europe |
| 32 | `he` | Hebrew | 141 | 9M | Middle East |
| 33 | `af` | Afrikaans | 140 | 8M | South Africa |
| 34 | `sq` | Albanian | 138 | 7M | Balkans |
| 35 | `es-SV` | Spanish (El Salvador) | 141 | 6M | Americas variant |
| 36 | `nb` | Norwegian | 143 | 5M | Scandinavia |
| 37 | `fi` | Finnish | 138 | 5M | Scandinavia |

**Effort:** ~2.5 hours with Copilot  
**ROI:** Adds 3% more population; completes European coverage

---

## TIER-4: Ultra-Niche (138 terms, 1 locale)

| Rank | Locale | Language | Terms | Speakers | Notes |
|------|--------|----------|-------|----------|-------|
| 38 | `et` | Estonian | 138 | 1M | Only if completionism is the goal |

---

## Strategy: Incremental Rollout

### Phase 1: TIER-1 Only
- **Translation effort:** ~3 hours
- **User coverage:** 53% of world population
- **Status:** Acceptable MVP for release

### Phase 2: Add TIER-2
- **Additional effort:** ~4 hours
- **Total coverage:** 80% of world population
- **Status:** Near-complete for release

### Phase 3: Add TIER-3
- **Additional effort:** ~2.5 hours
- **Total coverage:** 83% of world population + full European coverage
- **Status:** Comprehensive release

### Phase 4: Add TIER-4
- **Additional effort:** ~0.5 hours
- **Total coverage:** 100% of supported locales
- **Status:** Complete release

---

## Implementation Notes

1. **Use Copilot with Haiku** for all tiers — batch all locales in a tier together for 2-3x speed
2. **Telugu (te) warning:** 673 terms across 5 batch files — largest single locale, might need separate handling
3. **Greek (el) warning:** 191 terms across 2 batch files
4. **Spanish variants:** All 5 variants (es, es-MX, es-AR, es-CO, es-SV) should use same vocabulary — translate base (es) first, then variants
5. **Chinese variants:** Simplified (zh-CN) first, then Traditional (zh-TW) — shares ~90% vocabulary

---

## Effort Estimation (with Copilot/Haiku)

| Tier | Locales | Terms | Copilot Time | Notes |
|------|---------|-------|--------------|-------|
| TIER-1 | 11 | 1,553 | 3 hours | Batch in one Copilot session |
| TIER-2 | 15 | 2,681 | 4 hours | May need 2 sessions (⚠️ Telugu has 5 files) |
| TIER-3 | 11 | 1,542 | 2.5 hours | Low complexity, straightforward |
| TIER-4 | 1 | 138 | 0.5 hours | Trivial |
| **TOTAL** | **38** | **5,914** | **~10 hours** | Copilot finish all 38 locales in 1 day |

---

## When to Use This Ranking

- ✅ **Multi-release rollout:** Don't have time to translate all 38 at once
- ✅ **Phased deployment:** Release TIER-1 → test → release TIER-2, etc.
- ✅ **Budget constraints:** Translate high-impact locales first
- ✅ **Resource planning:** Estimate effort per tier
- ❌ **Full release:** Translate all at once (10 hours total, doesn't matter what order)

---

## Related

- [AI Locale Translation](./locale-ai-translation.md) — Translation workflow & church vocabulary
- [i18n & Localization](./i18n-localization.md) — Term consolidation, best practices
