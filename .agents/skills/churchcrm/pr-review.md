---
title: "PR Review"
intent: "Full pull request review workflow: fetch changes, validate standards, check docs/wiki, identify manual testing, address comments, and capture learnings"
tags: ["pr","review","code-quality","standards","workflow"]
prereqs: ["code-standards.md","git-workflow.md","github-interaction.md"]
complexity: "intermediate"
---

# Skill: Pull Request Review

## Context

This skill covers the full lifecycle of reviewing a PR: fetching all changes, validating them against ChurchCRM standards, identifying required documentation updates, specifying manual validation steps, addressing reviewer comments, resolving threads, and feeding learnings back into skills.

Use this skill whenever asked to:
- Review a PR or branch
- Check if a PR meets standards
- Respond to review comments
- Triage what manual testing is needed

---

## Phase 1 — Understand the PR

### Fetch full PR context

```bash
# Summary + metadata
gh pr view <NUMBER>

# All review comments (inline + top-level)
gh pr view <NUMBER> --comments

# JSON — reviews + inline comment threads
gh pr view <NUMBER> --json title,body,headRefName,baseRefName,state,latestReviews,reviews,comments

# Inline review threads (GraphQL — most complete)
gh api graphql -f query='
{
  repository(owner: "ChurchCRM", name: "CRM") {
    pullRequest(number: NUMBER) {
      reviewThreads(first: 50) {
        nodes {
          id
          isResolved
          comments(first: 3) {
            nodes { author { login } body path line }
          }
        }
      }
    }
  }
}'
```

### Understand scope

Before reading code, answer:
- What is the stated purpose of this PR?
- Which modules/files are in scope?
- Is this a bug fix, feature, refactor, or migration?
- Are there linked issues? (`Fixes #XXXX` in description)
- Are there open review threads or change requests?

---

## Phase 2 — Sync Branch with Master, Then Review Changes

**Always checkout the PR branch and merge master before reviewing or testing. This ensures you are reviewing code in its current merged state and avoids reviewing conflicts or stale diffs.**

### Step 1: Checkout and sync the branch

```bash
# Fetch all remote state
git fetch origin

# Checkout the PR branch (creates local tracking branch)
git checkout -b <branch-name> origin/<branch-name>
# or if branch already exists locally:
git checkout <branch-name>
git pull origin <branch-name>

# Merge master into the branch to bring it up to date
git merge origin/master
```

### Step 2: Resolve conflicts if any

If `git merge` reports conflicts:

```bash
# See which files conflict
git status

# For each conflicting file: resolve manually, then stage
git add <resolved-file>

# Finish the merge
git commit -m "Merge master into <branch-name> to resolve conflicts"

# Push the updated branch back to origin
git push origin <branch-name>
```

**Rules for conflict resolution:**
- Preserve the intent of the PR change — do not silently drop it
- Prefer master's version for infrastructure/shared files unless the PR explicitly changes them
- If the conflict is complex, describe what was resolved in the merge commit message
- After pushing the conflict-resolution merge, notify the PR author (leave a comment)

```bash
gh pr comment <NUMBER> --body "Merged master into branch to resolve conflicts. Please review the merge commit to ensure your changes are preserved correctly."
```

### Step 3: Verify the branch is up to date

```bash
# Should show "Already up to date" or your merge commit
git log origin/master..<branch-name> --oneline

# Confirm no divergence from origin/<branch-name>
git status
```

### Step 4: Review the full branch diff

**Always review the full branch diff, not just the latest commit.**

```bash
# Full diff against master (after sync)
git diff origin/master...<branch-name>

# All commits in branch (excluding the merge commit)
git log origin/master..<branch-name> --oneline

# Changed files summary
git diff --name-status origin/master...<branch-name>

# Diff of a specific file
git diff origin/master...<branch-name> -- path/to/file.php
```

### What to look for in the diff

- Are all changes directly related to the stated purpose of the PR?
- Are there any unintended changes (whitespace, unrelated files, debug code)?
- Is the scope appropriate — one issue per PR?
- Do commit messages follow the format from `git-workflow.md`?

---

## Phase 3 — Standards Compliance Checklist

Work through each section that applies to the changed files.

### PHP & Architecture

- [ ] PHP 8.4+ compatible — no deprecated patterns
- [ ] Explicit nullable params: `?int $param = null` not `int $param = null`
- [ ] `use` statements at top of file — no inline fully-qualified class names
- [ ] Dynamic properties annotated with `#[\AllowDynamicProperties]` if needed
- [ ] Global functions called with `\` prefix in namespaced code (`\MakeFYString()`)
- [ ] Propel/Perpl ORM used for all DB operations — no `RunQuery()` or raw SQL
- [ ] Dynamic IDs cast to `(int)`: `(int)$_GET['id']`
- [ ] Object properties accessed as `$obj->prop`, never `$obj['prop']`
- [ ] `=== null` checks used, not `empty()` for objects
- [ ] Service classes used for business logic (not controllers/routes/views)
- [ ] Critical files use `require` (Header.php, Footer.php), optional use `include`
- [ ] Algorithm complexity: no O(N×M) nested loops — use hash lookups
- [ ] Email failures: logged, not thrown as exceptions
- [ ] `LoggerUtils::getAppLogger()` used for logging — not `error_log()` directly

### Security

- [ ] `InputUtils::escapeHTML()` for body output, `escapeAttribute()` for attributes
- [ ] `RedirectUtils::redirect()` for redirects — not `header()` or `withHeader()`
- [ ] `SlimUtils::renderErrorJSON()` for API errors — not raw exceptions
- [ ] TLS verification enabled for outbound HTTPS requests
- [ ] User input validated at system boundaries
- [ ] Authorization checks present for protected routes
- [ ] No SQL injection risk — all queries use ORM or bound parameters

### Frontend & UI

- [ ] Bootstrap **4.6.2** classes only — not Bootstrap 5
  - ✅ `d-flex`, `w-100`, `mt-3`, `font-weight-bold`, `text-left`
  - ❌ `gap-4`, `d-grid`, `fw-bold`, `fs-5`, `w-full`, `text-start`
- [ ] Asset paths use `SystemURLs::getRootPath()` — no hardcoded `/skin/v2/`
- [ ] UI text wrapped with `gettext()` (PHP) or `i18next.t()` (JS)
- [ ] No `alert()` calls — use `window.CRM.notify()` instead
- [ ] No deprecated HTML attributes — use CSS equivalents
- [ ] Server-side initial state rendered to avoid JS-only flash

### i18n & Locale

- [ ] If new `gettext()` strings added: `npm run locale:build` was run
- [ ] If new `i18next.t()` strings added: `npm run locale:build` was run
- [ ] Updated `locale/terms/messages.po` committed with the changes
- [ ] Canonical UI terms used: `People` not `Persons`; `Active/Inactive` not `Deactivated`
- [ ] No compound terms that create duplicate translations (consolidate via concatenation)

### Database / Schema

- [ ] If schema changed: migration script added in `src/mysql/upgrade/`
- [ ] If migration added: `src/mysql/upgrade.json` updated with version entry
- [ ] New columns: nullable or have default — no breaking schema changes
- [ ] ORM schema.xml updated + Perpl classes regenerated (if schema changed)

### OpenAPI / API Documentation

- [ ] If new API endpoint added: `@OA\` annotations present
- [ ] If endpoint changed: annotations updated
- [ ] Named functions annotated above the `function` keyword
- [ ] Closures: standalone `@OA\` docblock above the `$group->get(...)` call with explicit `operationId`
- [ ] After annotation changes: `composer run openapi-public` or `openapi-private` regenerated

### Testing

- [ ] No debug code left: no `console.log`, `var_dump`, `dd()`, `dump()`
- [ ] No skipped tests: no `.skip` or `.only`
- [ ] New API endpoints have Cypress API tests in `cypress/e2e/api/`
- [ ] Critical UI flows have Cypress UI tests in `cypress/e2e/ui/`
- [ ] Cypress tests clear logs before running: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
- [ ] PHP logs reviewed after test runs (even on pass) for hidden 500s

### Git & Commits

- [ ] Branch name follows pattern: `fix/issue-NUMBER-description` or `feature/description`
- [ ] Commit messages: imperative mood, < 72 chars, no file paths, reference issue number
- [ ] No commented-out code blocks
- [ ] No TODO/FIXME comments (remove or create a GitHub issue)
- [ ] No debug files or temporary files committed

---

## Phase 4 — Documentation Requirements

For each type of change, determine what docs need updating:

| Change type | Required doc update |
|-------------|---------------------|
| New feature / user-visible behaviour | `docs.churchcrm.io/` user docs page |
| New admin setting or config option | `docs.churchcrm.io/` admin docs |
| New API endpoint or changed response | OpenAPI annotations + `CRM/openapi/*.yaml` + docs site MDX |
| Breaking change | Release notes + migration guide |
| New architectural pattern | Relevant skill file in `.agents/skills/churchcrm/` |
| Complex multi-step admin procedure | GitHub Wiki article |
| Plugin interface change | `plugin-system.md` + wiki |
| DB schema change | `db-schema-migration.md` pattern + upgrade script comment |

### Checking if docs are needed

```bash
# Has the feature touched user-facing UI text, settings, or API contracts?
git diff origin/master...origin/<branch> -- 'src/**/*.php' 'src/**/*.js' 'react/**/*.tsx'

# Are there new routes?
git diff origin/master...origin/<branch> -- 'src/api/routes/' 'src/admin/routes/'
```

### Updating docs.churchcrm.io

```bash
# Docs site is in docs.churchcrm.io/ repo
cd ../docs.churchcrm.io

# Regenerate OpenAPI MDX if API annotations changed
npm run regen

# Push to main — auto-deploys in ~90 seconds
git add . && git commit -m "Update docs for PR #NUMBER" && git push
```

### Updating wiki

```bash
# Wiki is a separate git repo
git clone https://github.com/ChurchCRM/CRM.wiki.git
cd CRM.wiki
# Edit or create relevant .md files
git add . && git commit -m "Update wiki for feature X" && git push
```

See `wiki-documentation.md` for article structure guidelines.

---

## Phase 5 — Manual Validation Requirements

After code review, identify what cannot be validated by automated tests alone:

### Always check manually
- Visual rendering in browser (Bootstrap classes, layout)
- Form validation UX and error messages
- Authentication/authorization flows (can an unauthorized user access the route?)
- Any i18n text changes (does the UI look right in English?)

### Check if applicable
| Scenario | Manual test |
|----------|-------------|
| DB migration added | Run migration locally, verify schema, verify app still boots |
| Email functionality | Trigger email, verify delivery, check logs |
| File upload/download | Upload a file, verify stored correctly, download |
| Plugin changes | Enable/disable plugin, verify no conflicts |
| Config setting changed | Toggle setting, verify behaviour changes |
| OpenAPI spec changed | Load Swagger UI or docs site, verify endpoint renders correctly |
| Bootstrap class changes | View page on mobile (375px) and desktop (1440px) |
| i18n strings added | Switch to a non-English locale, verify no broken strings |

### Local test workflow

```bash
# 1. Fetch and checkout the branch (sync with master first — see Phase 2)
git fetch origin
git checkout -b <branch-name> origin/<branch-name> 2>/dev/null || git checkout <branch-name>
git merge origin/master   # Bring branch up to date; fix conflicts if needed

# 2. Build
npm run build:php   # Composer deps
npm run build       # Frontend assets

# 3. Start dev environment
npm run docker:dev:start

# 4. Clear logs before testing
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# 5. Run targeted Cypress tests
npx cypress run --e2e --spec "cypress/e2e/api/path/to/test.spec.js"

# 6. Review logs even if tests pass
cat src/logs/$(date +%Y-%m-%d)-php.log
cat src/logs/$(date +%Y-%m-%d)-app.log
```

---

## Phase 6 — Submitting a Review

### Approving / requesting changes

```bash
# Approve the PR
gh pr review <NUMBER> --approve --body "LGTM. Verified locally: [what you tested]."

# Request changes
gh pr review <NUMBER> --request-changes --body "Please address the following:
- [specific issue 1]
- [specific issue 2]"

# Leave a comment without approval/rejection
gh pr review <NUMBER> --comment --body "Some thoughts: ..."
```

### Inline comment (single line)

```bash
gh api repos/ChurchCRM/CRM/pulls/<NUMBER>/comments \
  --method POST \
  -f body="Comment text" \
  -f path="src/path/to/file.php" \
  -f commit_id="$(gh pr view <NUMBER> --json headRefOid --jq .headRefOid)" \
  -f line=42 \
  -f side=RIGHT
```

### Review comment best practices

- Be specific: quote the code and explain the expected pattern
- Reference the relevant skill file where the standard is documented
- Suggest a fix when possible — don't just flag problems
- Distinguish blocking issues from nits: use prefixes like **[blocking]**, **[nit]**, **[suggestion]**

---

## Phase 7 — Addressing Review Comments

When working on a PR that has received review comments:

```bash
# 1. Fetch all unresolved threads
gh api graphql -f query='
{
  repository(owner: "ChurchCRM", name: "CRM") {
    pullRequest(number: NUMBER) {
      reviewThreads(first: 50) {
        nodes {
          id
          isResolved
          comments(first: 1) {
            nodes { databaseId body path }
          }
        }
      }
    }
  }
}' --jq '.data.repository.pullRequest.reviewThreads.nodes[] | select(.isResolved == false) | {id, preview: (.comments.nodes[0].body | .[0:80])}'
```

### Fix → push → resolve threads

```bash
# After fixing all issues and pushing:

# 2. Resolve each thread
for thread_id in <id1> <id2>; do
  gh api graphql -f query="mutation {
    resolveReviewThread(input: {threadId: \"$thread_id\"}) {
      thread { isResolved }
    }
  }"
done

# 3. Post a follow-up summary comment
gh pr comment <NUMBER> --body "## Follow-up changes pushed

Addressed all review comments:
- [comment 1 summary]: [what was done]
- [comment 2 summary]: [what was done]

All threads resolved."
```

**Rule:** Never leave addressed threads unresolved. Always resolve them after pushing fixes.

---

## Phase 8 — Capture Learnings Back to Skills

When a PR review reveals a pattern or mistake that isn't yet documented:

### Decision: where does the learning live?

| Type of learning | Where to document |
|------------------|-------------------|
| Project-specific coding pattern | Relevant `churchcrm/*.md` skill file |
| ChurchCRM-specific standard (Bootstrap, ORM, etc.) | `code-standards.md` |
| Git / PR workflow rule | `git-workflow.md` or `github-interaction.md` |
| Security rule | `security-best-practices.md` or `authorization-security.md` |
| Frontend pattern | `frontend-development.md` or `bootstrap-adminlte.md` |
| i18n pattern | `i18n-localization.md` |
| Performance pattern | `performance-optimization.md` |
| Generic PR/GitHub behaviour | `pr-description-guidelines.md` |
| Something totally new | Create a new skill file, add to SKILL.md + README.md |

### How to update a skill

1. Open the relevant skill file
2. Find the closest existing section
3. Add the new pattern with:
   - Brief description of the rule
   - A `✅ CORRECT` code example
   - An `❌ WRONG` code example (if applicable)
   - One sentence explaining why
4. Update the `Last updated` date if present

### How to create a new skill

1. Create `CRM/.agents/skills/churchcrm/<new-skill>.md`
2. Add entry to `CRM/.agents/skills/churchcrm/SKILL.md` in the right category table
3. Add entry to `CRM/.agents/skills/README.md` under the matching section
4. If it's a workflow that belongs in the PR review checklist, add it to Phase 3 above

### Example — capturing a learning

> **Scenario:** A PR review comment flags that `SystemConfig::getValue()` was used for a boolean check instead of `SystemConfig::getBooleanValue()`.

Add to `development-workflows.md` (already documented there — no change needed).

> **Scenario:** A PR adds a new `AdminLTE` card component but uses inline `style` attributes instead of utility classes.

Add to `bootstrap-adminlte.md` under a "Cards" section:

```markdown
### Cards — Use utility classes, not inline styles

// ✅ CORRECT
<div class="card card-primary card-outline">

// ❌ WRONG — inline styles break dark mode and consistency
<div class="card" style="border-top: 3px solid #3c8dbc;">
```

---

## Related Skills

- [Git Workflow](./git-workflow.md) — commits, branch naming, pre-commit checklist
- [GitHub Interaction](./github-interaction.md) — gh CLI commands for reviews and comments
- [PR Description Guidelines](../pr-description-guidelines.md) — writing PR descriptions
- [Code Standards](./code-standards.md) — detailed coding rules
- [Security Best Practices](./security-best-practices.md) — security review items
- [Wiki Documentation](./wiki-documentation.md) — when/how to update the wiki
- [API Development](./api-development.md) — OpenAPI annotation patterns

---

Last updated: 2026-03-03
