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

## Hosted remote config — never prune External <!-- learned: 2026-09-22 -->

`CentralServices` on master and shipped 7.7.0 fetches:

- `https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json`
- `https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json`

`External` is an orphan hosting branch (root JSON only). It will never have a PR into master. Repo-health Check 4 deleted it on 21 Sep 2026 (#9961). The registry 404'd, `ApprovedPluginRegistry` stored `[]`, and UI shard 2 timed out waiting for `#approvedPluginsList .btn-install-approved` (#9969).

**Rules:**
- Never delete `External` or `Notifications`.
- Ruleset [23858586](https://github.com/ChurchCRM/CRM/rules/23858586) blocks deletion and force-push. A 422 on delete is success — stop.
- Registry changes: PR with base `External`, edit root `approved-plugins.json`.
- Keep `hello-world` on the allowlist until `community-plugin-lifecycle.spec.js` is retargeted.
- Current allowlist: hello-world 1.0.1, meeting-outlines 1.0.2.
- Last historical file before the prune: commit `d5902d02` / PR #8928.

Agent skills: `.agents/skills/churchcrm/hosted-remote-config.md`, `plugin-registry.md`, `repo-health.md`.

---

## Release notes and publishing <!-- learned: 2026-09-27 -->

Lessons from 7.7.1. The how-to is in `.agents/skills/churchcrm/release-notes.md`; these are the traps.

- **Never edit a draft release without `--tag`.** A draft has no git tag yet. `gh release edit <tag> --notes-file …` on the draft left it on GitHub's placeholder `untagged-<hash>`, and publishing shipped that as the version. Installs read the version from `tag_name` (`ChurchCRMRelease.php`), so every update check saw `untagged-…`. Always pass `--tag <version>`, then check `gh release view <version> --json tagName` before publishing.
- **Recover in this order:** create the real tag on the release commit, move the release onto it with `gh release edit untagged-… --tag <version>`, verify, and only then delete the stray tag. Deleting the tag first turns the release back into a draft. Then re-run `release-bookkeeping.yml` with `tag=<version>`.
- **Merge "Start <next> release" before publishing.** Bookkeeping reads the next version from master's `package.json`. If master still carries the released version, the milestone job now stops.
- **Apply notes from the approved branch,** with `git show origin/<branch>:changelog/<tag>.md`, not the local checkout.
- **Weight notes by reach, not PR size.** A brand change seen on every screen is a feature. A capability nobody uses yet (7.7.1's social links) gets one line.
- **Work in other repos is FYI.** Thank people for it only when it directly helps users of the release (artwork shipped in the app, a docs guide for a shipped feature). Add one "Documentation Caught Up" line when the docs were trued up.
- **Screenshots and videos are refreshed after publishing, never before,** so they always match the downloadable version.
- **The Actions token can't read `ChurchCRM/marketing`** (private) or write docs milestones. Set `DOCS_RELEASE_TOKEN`, and re-run the context script locally for marketing contributors.

---

## Related Skills

- [Plugin System](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/plugin-system.md) — How plugins define features
- [Database Operations](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/database-operations.md) — ORM schema queries
- [API Development](https://github.com/ChurchCRM/CRM/blob/master/.agents/skills/churchcrm/api-development.md) — Feature exposure through APIs

---

Last updated: 2026-09-22
