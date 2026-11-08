# Proposal: Individual Person Pledge & Payment Attribution Sidecar Mapping

**Upstream Issue**: [#11308](https://github.com/ChurchCRM/CRM/issues/11308)  
**Related Epic**: [#9313](https://github.com/ChurchCRM/CRM/issues/9313)  
**Related RFC**: [RFC 0004](../decisions/0004-portal-giving-pledges.md)  
**Date**: 2026-11-08  

***

### What would you like to see?

A non-invasive individual person pledge and payment attribution mechanism linking pledge and payment records (`pledge_plg`) to individual family members (`person_per`) via a lightweight sidecar mapping table (`person_pledge_map`).

This builds directly on the goals of canonical epic #9313 (Feature: Optional Individual Contribution Tracking) and aligns with the donor-grain direction in RFC 0004 (GIV-05 per-person attribution).

Key capabilities:
1. Sidecar mapping table `person_pledge_map` (`ppm_plg_id` PK, `ppm_per_id` FK/index) leaving core `pledge_plg` schema untouched and 100% backward compatible.
2. Optional individual attribution dropdown in the pledge editor (`editor.php`) populated dynamically with members of the selected family.
3. Attribution display in the Finance Dashboard (`pledge-dashboard.php`), Family Pledge Summary (`family-summary.php`), Fund Contributors (`contributors.php`), and detail view (`detail.php`).
4. Full cascade cleanup on pledge group updates and deletions.
5. Standard idempotent migration path registered in `upgrade.json` and `Install.sql`.

### Why does it matter?

In standard ChurchCRM, pledges and payments attach exclusively to a `Family` entity (`plg_FamID`). While household tracking is sufficient for single-earner households, many churches need to record which specific individual made a contribution:
- Individual tithes: spouses contributing separately for personal stewardship tracking.
- Building funds and capital campaigns: individual family members fulfilling distinct pledges.
- Ministry/auxiliary funds: youth, women's, or men's fellowship contributions attributed to specific individuals.

When multiple adults share a family record, attributing giving to an individual currently requires creating artificial single-person families, which fragments household directories and communications.

### Proposed Architecture & Compatibility

- Zero core table modifications: Core `pledge_plg` remains unmodified. Existing installations without person attribution operate with zero behavioral changes.
- Batched lookups: `PersonPledgeService::getPersonsForPledges()` and `getPersonNames()` batch queries into single `IN (...)` operations, eliminating N+1 queries on finance dashboards.
- Household aggregation intact: All existing family totals, tax statements, and fiscal year summaries continue aggregating by `plg_FamID`.
- Verification: Covered by Cypress end-to-end tests exercising pledge assignment, detail view attribution, and finance dashboard reporting.
