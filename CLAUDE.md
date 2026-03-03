# ChurchCRM Development Guide

This file is loaded by Claude Code for every session. Follow these instructions for all work on this project.

---

## Skills System

Structured development skills live in `.agents/skills/`. **Always consult the relevant skill before starting work.**

- **Index**: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md) — use this to find the right skill for your task
- **Generic skills**: `gh-cli`, `interface-design`, `php-best-practices`, `web-design-guidelines` (see `.agents/skills/`)

### Skill Selection by Task

| Task type | Skills to read |
|-----------|---------------|
| New API endpoint | `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` |
| Migrate legacy page | `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` |
| Database / ORM work | `database-operations.md` → `db-schema-migration.md` |
| UI / frontend changes | `bootstrap-adminlte.md` → `frontend-development.md` → `webpack-typescript.md` |
| i18n / translations | `i18n-localization.md` → `frontend-development.md` |
| Security issue | `security-best-practices.md` → `authorization-security.md` |
| Plugin work | `plugin-system.md` → `plugin-development.md` |
| Testing | `testing.md` → `cypress-testing.md` |
| Commit / PR | `git-workflow.md` → `github-interaction.md` |
| Refactor | `refactor.md` → `service-layer.md` |
| Performance | `performance-optimization.md` → `database-operations.md` |
| Configuration | `configuration-management.md` |

---

## Auto-Learning: Proactive Skill Updates

**IMPORTANT: Agents must update skill files automatically when they learn something new — no user prompt required.**

### When to Update Skills

Update the relevant skill file immediately when you:
- Discover a pattern, API, or convention not yet documented
- Find a bug, gotcha, or anti-pattern worth warning others about
- Solve a recurring problem with a reusable solution
- Confirm that documented guidance is wrong or outdated
- Encounter a new file, class, or service that belongs in the architecture overview

### What Qualifies as "New Learning"

- A class, utility, or helper you had to search for (others will too)
- A constraint you violated and had to fix (e.g., wrong Bootstrap class, missing cast)
- An edge case in Propel ORM, Slim 4, or AdminLTE not in existing docs
- A build/test step that's easy to forget
- A new module, route group, or architectural pattern added to the codebase

### What Does NOT Qualify

- Trivialities already covered in existing skill files
- Task-specific context (e.g., "today I fixed issue #1234")
- Speculation — only write confirmed, tested facts
- Anything that duplicates existing documented guidance

### How to Update

1. **Identify the right skill file** from `.agents/skills/churchcrm/SKILL.md`
2. **Edit the skill file** — add a clearly labelled subsection with a short explanation + code example
3. **If it's a new category**, add a row to the table in `.agents/skills/churchcrm/SKILL.md`
4. **Keep it concise** — one paragraph max, prefer code examples over prose
5. **Date the entry** — append `<!-- learned: YYYY-MM-DD -->` as an HTML comment on the section header line

### Example Auto-Update (what to write)

```markdown
### Casting Foreign Keys in Propel Relations <!-- learned: 2026-02-28 -->

When traversing Propel relations via `->getXxx()`, always cast the FK to `(int)`
before passing to query methods — Propel does not auto-cast string inputs from
`$_POST`/route params.

```php
// ✅ CORRECT
$group = GroupQuery::create()->findPk((int)$groupId);

// ❌ WRONG — silently returns null when $groupId is a string "42"
$group = GroupQuery::create()->findPk($groupId);
```
```

### Memory File Sync

After updating a skill file, also check if [`.claude/projects/.../memory/MEMORY.md`] needs a one-line summary added under **Critical Patterns**.

---

## Always-Apply Standards

These rules apply to **every code change** in this project.

@.agents/skills/churchcrm/code-standards.md

---

## Git & PR Workflow

@.agents/skills/churchcrm/git-workflow.md

---

## Test Review & Commit Workflow

**MANDATORY: Always test changes to test files BEFORE committing.**

When fixing a failed test:

1. **Run the failing test in isolation** — use `--spec` flag to test only that file
2. **Identify root cause** — check logs, API responses, or browser errors
3. **Update relevant skills** — if you discover a pattern, add it with `<!-- learned: YYYY-MM-DD -->`
4. **Update master SKILL.md** — if it's a new testing category, add row to skill index
5. **Commit with documentation**:
   ```
   test: fix {test name} - {reason}
   
   - Root cause: {what was wrong}
   - Fix: {what changed}
   - Updated: cypress-testing.md with {pattern/learning}
   - Requires: Docker|Local environment
   ```

### Test-Related Skills to Update

- `cypress-testing.md` — API patterns, session setup, data handling
- `database-operations.md` — ORM query patterns
- `webpack-typescript.md` — React/component patterns
- `code-standards.md` — General best practices

**Remember: Skills get documented the moment you learn something. Never defer skill updates.**
