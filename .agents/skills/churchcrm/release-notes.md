# Skill: Release Notes Authoring

## Purpose

Generate polished, end-user-focused GitHub release notes for a ChurchCRM version. Release notes are the primary communication channel to church administrators — write for **people, not developers**.

---

## When to Use This Skill

Use before publishing a GitHub release when you need to:
- Summarize changes from a previous tag to the current release
- Translate technical commits into user-facing language
- Follow the established ChurchCRM release notes format

---

## Step 1 — Gather the Raw Data

Run these commands before writing anything:

```bash
# 1. Find the previous release tag
git tag | sort -V | tail -10

# 2. Get all non-merge commits since that tag
git log <PREV_TAG>..HEAD --oneline --no-merges

# 3. Get only feature commits
git log <PREV_TAG>..HEAD --format="%s" | grep -E "^feat"

# 4. Get only fix/security commits
git log <PREV_TAG>..HEAD --format="%s" | grep -iE "^(fix|security|XSS|vuln)"

# 5. Get files with most changes (for impact assessment)
git diff <PREV_TAG>..HEAD --stat | sort -t'|' -k2 -rn | head -30

# 6. Count locale languages
ls src/locale/i18n/ | wc -l

# 7. Check current version
grep '"version"' package.json | head -1
```

Also fetch the **previous release notes from GitHub** to match tone and format:
```
gh release view <PREV_TAG>
```
or visit: `https://github.com/ChurchCRM/CRM/releases/tag/<PREV_TAG>`

---

## Step 2 — Classify Commits

Sort raw commits into these user-facing buckets before writing:

| Bucket | What goes here | Source patterns |
|--------|----------------|-----------------|
| **New Features** | Visible new functionality | `feat:`, `Feature:` |
| **Security** | CVEs, XSS, injection, auth fixes | `fix.*XSS`, `Fix.*vuln`, `Fix.*redirect`, `fix.*inject` |
| **UI/UX** | Layout, styling, accessibility | Bootstrap, AdminLTE, DataTables, modal, form changes |
| **Localization** | Languages, translations, browser detection | `locale`, `i18n`, `🌍`, `translation` |
| **Platform** | PHP version, Node version, ORM, framework | `PHP`, `Node`, `Propel`, `Slim`, `Monolog` |
| **Dependency** | Major library upgrades with user impact | Major semver bumps (v1→v2, etc.) |
| **Removed** | Deprecated features, dead providers | Mentions of removal, deletion, retirement |
| **Ignore** | CI/CD, internal tooling, test-only, formatting | `ci:`, `chore:`, `style:`, `test:`, `docs:` |

**Rule:** If a commit only affects CI, linting, or developer tooling — skip it. If it affects what the end user sees or how they upgrade — include it.

---

## Step 3 — Write the Release Notes

### Format Template

Follow this structure exactly (with emoji section markers matching ChurchCRM's established style):

```markdown
# 🎆 ChurchCRM X.Y.Z — [Tagline]

> *[One-sentence theme for this release]*

Released: [Month Year]

---

[2–3 sentence intro paragraph. What's the story of this release?]

---

## 🔤 [Feature Section 1]

[Narrative paragraph explaining the user benefit, not the implementation]

**What changed for you:**
- [Bullet: end-user impact, not code detail]
- [Bullet]
- [Bullet]

---

## 🛡️ Security

[Brief intro about the security focus]

- **[Vulnerability type] fixed in [feature area]** — [what an attacker could have done, past tense]. Fixed and covered by automated tests.
- [Next vulnerability]

---

## 🌍 [Language Count] Languages — Localization

[Intro about translation progress]

**New in this release:**
- [Bullet for new locale feature]
- [Bullet for updated languages with specifics]

---

## ⚙️ Under the Hood

### [Platform change 1]
[1–2 sentence user-facing explanation]

### [Platform change 2]
[...]

---

## 🗑️ Removed in X.Y.Z

| Removed | Replacement |
|---------|-------------|
| [Feature] | [What replaces it] |

---

## 🎁 Looking Ahead

[2–3 sentences about direction. Positive, forward-looking.]

---

> 💡 **Upgrading from X.x?** [Any must-know upgrade steps. PHP version requirement, config migration, plugin re-setup, etc.]

---

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/<PREV_TAG>...<NEW_TAG>
```

---

## Step 4 — Writing Rules

### Voice & Tone
- Write for **church administrators**, not engineers
- Use **present or past tense** for existing features, **present tense** for what's new
- Avoid: "refactored", "migrated", "bumped", "chore", "CI", "linting"
- Use instead: "replaced", "upgraded", "improved", "now supports", "fixed"
- **Lead with the benefit**, not the change: "Maps now work without a Google API key" not "Removed Google Maps dependency"

### Structure Rules
- Each major feature gets **its own section** with an emoji header
- **Security always gets its own section** — never bury it
- **Localization always gets its own section** — it matters to international users
- Use a **Removed table** for anything deleted — users need to know
- One **upgrade callout blockquote** at the end for important upgrade notes

### What to Include vs. Skip

| Include | Skip |
|---------|------|
| New user-visible features | CI/CD workflow changes |
| Security vulnerability fixes | Code formatting/linting changes |
| Dependency upgrades that affect UX | Test-only changes |
| PHP/Node version requirements | Internal refactors with no user impact |
| UI consistency fixes visible to users | Developer tooling changes |
| Language/translation changes | Chore commits |
| Breaking changes or removed features | Duplicate commits (pick one) |

### Handling Major Versions (X.0.0)

For major releases:
- Open with a **milestone/anniversary framing** if applicable
- Lead with the **single biggest feature** as the first full section
- Include a **migration warning** at the end (PHP version, config changes, data migration)
- The tone should feel **celebratory but informative**

### Handling Minor Versions (X.Y.0)

- Focus on **2–4 feature themes** rather than exhaustive lists
- Group small fixes under "Stability & Performance" rather than listing each one
- Shorter overall — 400–700 words is appropriate

### Handling Patch Versions (X.Y.Z)

- Lead with the most important fix
- Use a **short bulleted list** — no long narrative sections
- Always call out security fixes first
- 150–300 words total

---

## Step 5 — ChurchCRM-Specific Vocabulary

Use church-appropriate language when describing user-facing features:

| Generic CRM term | ChurchCRM term |
|---|---|
| Members | Congregation / Parishioners |
| Contacts | Members / Families |
| Groups | Ministries / Small Groups |
| Donations | Offerings / Tithes / Contributions |
| Users | Administrators / Staff |
| Leads | Visitors / Seekers |

---

## Step 6 — Save to the Changelog Folder

**Always** save the finished release notes as a file in `changelog/` before publishing:

```bash
# File name = version number
changelog/7.0.0.md
changelog/6.8.1.md
changelog/6.8.2.md
```

Then update `CHANGELOG.md` at the repo root to add a row to the releases table:

```markdown
| [7.0.0](./changelog/7.0.0.md) | February 2026 | Plugin system, Leaflet maps, PHP 8.4, 10th anniversary |
```

The row format is: `| [VERSION](./changelog/VERSION.md) | Month Year | Comma-separated highlights |`

**Rule:** The file in `changelog/` is the source of truth. The GitHub release body is a copy of it.

---

## Step 7 — Quality Checklist

Before finalizing:

- [ ] Notes saved to `changelog/X.Y.Z.md`
- [ ] `CHANGELOG.md` table updated with new row
- [ ] No raw commit hashes or branch names visible
- [ ] No developer jargon (CI, linting, refactor, bump, chore)
- [ ] Security section present (if any security fixes)
- [ ] Language count is accurate (`ls src/locale/i18n/ | wc -l`)
- [ ] Upgrade notes cover PHP version change (if any) — use a prominent `⚠️` blockquote
- [ ] Removed features table is present (if anything was removed)
- [ ] Full Changelog link at the bottom with correct tags
- [ ] Tone matches the release type (major = celebratory, patch = focused)
- [ ] Every section has at least one concrete user benefit stated

### PHP Version Alert Rule

If the minimum PHP version changed, the upgrade callout **must** use `⚠️` and include explicit steps:

```markdown
> ⚠️ **BEFORE YOU UPGRADE — PHP X.X IS REQUIRED**
>
> ChurchCRM X.Y.Z **will not run on PHP X.X or earlier**. If your hosting
> environment has not been upgraded, **do not start the upgrade** — your site will break.
>
> **Steps to take before upgrading ChurchCRM:**
> 1. Log into your hosting control panel (cPanel, Plesk, etc.)
> 2. Switch your PHP version to **X.X** or later
> 3. Confirm your site still loads on the current version
> 4. Then proceed with the ChurchCRM upgrade
```

---

## Example: Translating Commits

**Raw commits:**
```
fix: prevent XSS in person-list filter dropdowns
Fix stored XSS in Person Property Management subsystem
Fix open redirect vulnerability via linkBack URL parameter
chore(deps): bump phpmailer/phpmailer from 6.12.0 to 7.0.2
feat(maps): install Leaflet.js v1.9.4 + replace Google Maps widget
🌍 Locale update from POEditor on 2026-02-25
ci: harden all GitHub Actions for reliability
style: apply Biome format and lint fixes
```

**After classification:**
- Security: XSS × 2, open redirect
- Features: Maps (Leaflet)
- Platform: PHPMailer upgrade (note it if major semver)
- Localization: POEditor update
- Skip: CI hardening, Biome formatting

**Written output (security section excerpt):**
```markdown
## 🛡️ Security

This release patches three vulnerabilities discovered during our security audit:

- **Stored XSS fixed in Person Properties** — a crafted property value could
  execute script in an admin's browser. Patched and covered by automated tests.
- **XSS fixed in person-list filters** — search filter inputs now properly escape
  injected markup before rendering.
- **Open redirect blocked** — the `linkBack` parameter now validates that
  redirects stay within ChurchCRM. External redirects are rejected and logged.
```

---

## Step 8 — Social Media Posts

After saving to `changelog/` and publishing the GitHub release, generate social media posts for all four platforms:

```
→ social-media-release.md
```

Platforms: X / Twitter · Facebook · Instagram · LinkedIn

Run this skill with the changelog file as context. It handles platform-specific formatting, character limits, hashtags, and tone automatically.

---

## Related Skills

- [Git Workflow](./git-workflow.md) — tagging, branching for releases
- [GitHub Interaction](./github-interaction.md) — publishing the release via `gh`
- [Social Media Release](./social-media-release.md) — X, Facebook, Instagram, LinkedIn posts
- [i18n & Localization](./i18n-localization.md) — locale details for the notes
- [Locale AI Translation](./locale-ai-translation.md) — AI translation pipeline before release
