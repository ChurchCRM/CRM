# ChurchCRM Development Memory

Critical patterns, learnings, and conventions for maintaining and improving ChurchCRM.

---

## Product Claim Verification — Cross-Repo Coordination <!-- learned: 2026-09-05 -->

When the marketing website (churchcrm.io) makes claims about ChurchCRM features, those claims must be verified against the actual product codebase. This prevents marketing-code misalignment and ensures user expectations match reality.

### Check Locations

When auditing or verifying a feature claim:

1. **Database schema** — `orm/schema.xml`: Check if the database structure supports the claimed feature
   - Example: Checking `volunteer_opportunities` table for skill/availability columns
   - If claimed fields don't exist in schema, the feature doesn't exist

2. **Plugin capabilities** — `src/plugins/core/`: Core plugins define what features are available
   - Example: Volunteer plugin only has: name, description, active, order
   - No skills or scheduling fields = those claims are unsupported

3. **GitHub workflows** — `.github/workflows/`: Automated tests, builds, CI/CD reveal real feature support
   - Features with strong test coverage are reliable
   - Undocumented/untested claims are risky

4. **Documentation** — docs.churchcrm.io: Confirmed user-facing capabilities
   - Documentation reflects what users can actually do
   - Undocumented features may be incomplete or abandoned

### Example: Volunteer Opportunity Audit (Issue #56)

**Marketing Claim:** "Volunteer skills and availability tracking"

**Audit Result:** 
- **Database**: volunteer_opp table has only: `oppID`, `oppName`, `oppDesc`, `oppActive`, `oppSort` (NO skills/availability fields)
- **Plugin Code**: `src/plugins/core/volunteeropportunities/` only manages name/description/status, no scheduling
- **Tests**: No Cypress tests for skill matching or availability calendars
- **Conclusion**: Claim is unsupported — reword to describe what actually exists

**Fix Applied:** Rewording to "Organize volunteer opportunities by name and description with active/inactive status" (accurate to actual code)

### When to Trigger Verification

Verify feature claims when:
- Writing or updating marketing copy (churchcrm.io, blog posts, README)
- Reviewing architecture or schema changes that may affect claims
- Auditing plugin capabilities before recommending features to users
- Ensuring a feature claim still holds true after refactoring/deprecation

### Communication Pattern

If marketing claims don't match product reality:
1. Document the gap (schema check, code inspection, test results)
2. Reword the claim to match actual capabilities
3. Do NOT implement the claimed feature in the product just to match marketing (wrong direction)
4. Update documentation (docs.churchcrm.io) if the feature exists but was underdocumented

### Cross-Repo References

- **Marketing repo**: `/home/user/churchcrm.io/` — uses claims that must be verified
- **Product repo**: `/home/user/CRM/` — source of truth for what features exist
- **Documentation repo**: `/home/user/docs.churchcrm.io/` — user-facing capability reference

---

## Related Skills

- [Plugin System](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/plugin-system.md) — How plugins define features
- [Database Operations](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/database-operations.md) — ORM schema queries
- [API Development](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/api-development.md) — Feature exposure through APIs

---

Last updated: 2026-09-05
