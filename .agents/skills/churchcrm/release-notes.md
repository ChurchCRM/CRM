---
title: "Release Notes Authoring"
intent: "Transform a generated ChurchCRM GitHub draft changelog into accurate, user-focused release notes"
tags: ["release", "documentation", "workflow"]
prereqs: ["[[release-management]]", "[[github-interaction]]"]
complexity: "beginner"
---

# Skill: Release Notes Authoring

## Purpose

Transform the raw notes in an already-created GitHub **draft release** into clean, engaging, user-focused ChurchCRM release notes in GitHub-Flavored Markdown.

This skill is an editorial and verification step. It does not decide release scope and does not publish the release.

## Authoritative Input

The normal release flow creates the draft first through `.github/workflows/release-publish.yml`.

Use the GitHub-generated draft notes as the raw changelog. Verify the transformed notes against the actual PRs/commits included between the previous release and the draft release SHA.

Do not make the old process of independently generating release scope from local commit logs the source of truth.

---

## Role & Objective

Act as an expert technical writer and release manager.

Bridge the gap between technical changes and end-user value. Focus on how verified changes improve daily workflows, usability, administration, and internationalization for non-technical ChurchCRM users.

Never claim a benefit that the shipped change does not support. A refactor does not automatically mean faster performance, better security, or improved usability.

---

## Required Format

### Header

Use:

```markdown
# [Emoji] ChurchCRM [Version] — The "[Title / Focus Name]" Release

**Release Date**: [Date]
**Theme**: [3-4 Key Highlights]
```

Follow with a 2–3 sentence summary of the main verified user benefits.

### Primary Sections

Use Level 2 Markdown headers and separate major sections with `---`.

When applicable, group content into:

```markdown
## ✨ Exciting New Features
## 🛠️ Enhancements & Improvements
## ⚙️ Administration & Ongoing Platform Safety
## 🌍 Global Language Polish
```

Use Level 3 headings for distinct features or categorical groupings such as Financials, Directory, Administration, or Usability.

Do not create an empty section merely to satisfy the template.

### Bullets

Use itemized bullets under subheadings and bold the leading concept:

```markdown
* **Feature Name:** Clear user-focused description.
```

### Closing

End with the full technical comparison link:

```markdown
**Full Technical Changelog**: [Compare X...Y](https://github.com/ChurchCRM/CRM/compare/X...Y)
```

Then add a brief, warm closing statement.

---

## Writing Rules

- Write for church administrators, staff, and volunteers rather than developers.
- Lead with verified user value, not implementation details.
- Avoid developer jargon such as refactor, lint, CI, chore, or internal route names unless users genuinely need the information.
- Internal-only changes may be omitted when they have no meaningful user or upgrade impact.
- Do not include raw commit hashes, source/citation artifacts, `end_span` markers, or other model/tool metadata.
- Output standard Markdown only. Do not use HTML.
- Do not invent features, performance improvements, security outcomes, compatibility claims, or user benefits.
- Do not turn routine code refactoring into claims such as "faster page loads" without supporting evidence.
- Breaking changes, removed functionality, runtime requirements, and required upgrade actions must be stated clearly.

### Security and Platform Safety

Routine security/bug maintenance should use calm background-maintenance framing, for example:

> Routine maintenance and continuous protections have been applied across the platform.

Do not use alarmist language.

However, if the release includes a published security advisory, CVE, required security upgrade, or other disclosure that users need in order to act safely, describe it accurately. Do not minimize or hide required security information.

### Localization

Do not hard-code a language or locale count.

Verify the current authoritative ChurchCRM localization state before writing the section. Use the verified value in a sentence equivalent to:

> The system is fully localized across [verified count] supported locales for all the new features and updates in this release.

Only say "fully localized" when current product/localization evidence supports that claim. If it does not, describe the actual translation state.

---

## Transformation Process

1. Retrieve the draft GitHub release created by the canonical release workflow.
2. Identify the previous public release/tag and the draft release target SHA.
3. Read the generated draft changelog.
4. Inspect the actual shipped PRs/commits when necessary to understand user impact.
5. Classify changes into user-facing features, enhancements, administration/platform safety, localization, upgrade requirements, removals, and internal-only work.
6. Rewrite the draft using the required format.
7. Verify every material claim against the shipped changes.
8. Verify version, release date, comparison tags/link, localization statement, and upgrade requirements.
9. Present the polished notes for George's review before publication.

The draft release is the starting source; repository/product truth is the fact-checking authority.

---

## Quality Gate

Before the notes are approved:

- [ ] Version and release target match the draft.
- [ ] Every material feature claim is supported by a shipped change.
- [ ] No speculative performance/usability/security benefit was added.
- [ ] Important user-visible changes are not buried in technical language.
- [ ] Required upgrade/runtime warnings are clear.
- [ ] Security wording is accurate and appropriately framed.
- [ ] Localization count/status was verified rather than copied from an old release.
- [ ] Internal CI/test/refactor noise is omitted unless it affects users or upgrades.
- [ ] Comparison link uses the correct previous and new tags.
- [ ] Output is GitHub-Flavored Markdown with no citation/tool artifacts.
- [ ] George has an opportunity to review the final notes before publication.

---

## Relationship to Release Management

This skill is invoked from `release-management.md` only after the draft GitHub release exists.

The normal sequence is:

`release readiness → draft release → release-note transformation/fact-check → George publication approval → publish → post-release verification`

After publication, approved communications can proceed through the project's established community/social workflow.
