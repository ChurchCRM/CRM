# Git Workflow & Development Standards

Guidelines for branching, commits, PRs, and pre-commit validation to maintain code quality.

---

## Branching Strategy

### Branch Naming Convention

All feature branches must follow a consistent naming pattern:

```
fix/issue-{NUMBER}-{description}
feature/{description}
```

**Examples:**
- `fix/issue-7698-replace-bootstrap-5-classes` - Bug fix for issue #7698
- `fix/CVE-2024-12345-sql-injection` - Security fix for CVE
- `feature/add-dark-mode-toggle` - New feature
- `fix/issue-8101-api-response-timeout` - API fix

**Rules:**
- Use kebab-case (lowercase with hyphens)
- Include issue number for bug fixes: `fix/issue-{NUMBER}`
- No spaces or underscores
- Keep descriptions concise (< 50 chars)
- One branch per issue

### Branch Lifecycle

```
master (default branch)
  ↓
git checkout master
git checkout -b fix/issue-1234-description
  ├─ [Make changes, test locally]
  ├─ [Run tests]
  ├─ [Stage & commit]
  │  git add .
  │  git commit -m "Fix issue #1234: ..."
  └─ [Push to GitHub]
     git push origin fix/issue-1234-description
       ↓
     [Create PR on GitHub]
       ↓
  [Review, approve, conflicts resolved]
       ↓
  [Merge to master]
       ↓
  [Delete feature branch]
     git branch -d fix/issue-1234-description
```

---

## Commit Message Format

### Structure

```
{action} issue #{number}: {description}
```

### Requirements

1. **Imperative mood** - Use command form, not past tense
2. **Reference issue number** - Include `#NUMBER` if fixing an issue
3. **< 72 characters** for subject line (soft limit: 80 hard limit)
4. **No file paths** - Don't mention which files changed
5. **Clear purpose** - What changed and why

### Examples

**✅ CORRECT:**
```
Fix issue #7698: Migrate dropdown to Tabler action menu pattern
Add email validation to contact signup form
Fix null pointer exception in payment processing
Refactor user authorization checks for clarity
Update MailChimp sync interval configuration
```

**❌ WRONG:**
```
Fixed the bug in src/EventEditor.php                    # Not imperative, includes path
Fix issue #7698                                          # Too vague
Updates to multiple files for consistency                # Not imperative
Closes issue #6672: Renumber group property fields      # Should be "Fix", not "Closes"
Fix/issue/7698/replace bootstrap classes                # Wrong format, not imperative
```

### Multi-line Commit Messages

For complex changes, use multi-line format:

```
Fix issue #7698: Migrate dropdown to Tabler action menu pattern

Updated dropdown triggers to use btn-ghost-secondary + ti-dots-vertical.
Replaced dropdown-menu-right with dropdown-menu-end. Removed aria-haspopup
and inline styles. Fixed overflow clipping in table-responsive containers.

Files changed:
- src/admin/views/*.php (3 files)
- src/finance/views/*.php (2 files)
- webpack/admin.js (styling imports)

Tests: All existing tests pass. Added new test for form responsiveness.
```

**Format:**
- Line 1: Subject (< 72 chars, imperative, reference issue)
- Line 2: Blank line
- Lines 3+: Detailed explanation (what, why, impact)
- Can include testing, migration steps, or breaking changes

---

## Pull Request Organization

### Creating a PR

**Rules:**
- One issue per PR (don't mix multiple fixes)
- All commits belong to single feature branch
- Branch created from `master`, not other branches
- All tests passing locally before pushing

**Workflow:**
```bash
# 1. Start from master
git checkout master
git pull origin master

# 2. Create feature branch
git checkout -b fix/issue-NUMBER-description

# 3. Make changes, test, commit
[edit files...]
npm run build          # Rebuild bundles
npm run test          # Run cypress tests
git add .
git commit -m "Fix issue #NUMBER: ..."

# 4. Push and create PR
git push origin fix/issue-NUMBER-description
# Open PR on GitHub with description
```

### PR Description Format

PR descriptions must follow a structured format in the GitHub UI:

```markdown
## Summary
Brief overview of what this PR accomplishes (2-3 sentences).

## Changes
- Migrated dropdowns to Tabler action menu pattern (btn-ghost-secondary, ti icons)
- Updated form styling in 3 admin pages
- Fixed responsive grid breakpoints for mobile devices
- Added tests for form validation on small screens

## Why
- Bootstrap 5 classes incompatible with AdminLTE v3.2.0
- Fixes issue #7698 (broken page layout on mobile)
- Improves accessibility on small screens

## Files Changed
- `src/admin/views/dashboard.php` - 12 insertions, 3 deletions
- `src/admin/views/settings.php` - 8 insertions, 2 deletions
- `webpack/admin.js` - 5 insertions, 1 deletion

## Testing
- ✅ All existing tests pass
- ✅ Added new responsive grid tests
- ✅ Tested on mobile (iPhone 12, Android)
- Tests: `npm run test -- --spec "cypress/e2e/ui/admin/*.spec.js"`

## Related Issues
Fixes #7698
```

**Requirements:**
- **Summary** - What does this PR do? (use imperative mood)
- **Changes** - Bulleted list of what changed (organized by feature/file)
- **Why** - Motivation and benefits
- **Files Changed** - List of modified files
- **Testing** - How to verify, test commands
- **Related Issues** - Links to related issues/PRs

### Keeping PR Descriptions Up to Date <!-- learned: 2026-03-29 -->

After the initial PR is created, **update the PR description whenever the scope of changes evolves** — e.g., after addressing review comments that add/remove files or features, or after merging master resolves conflicts that affect the stated changes.

Use `gh pr edit` to update in place:

```bash
gh pr edit 1234 --body "$(cat <<'EOF'
## Summary
...updated summary...

## Changes
- Original change
- New change added during review

## Why
...

## Testing
...
EOF
)"
```

**Rule:** The description must always accurately reflect *what the PR actually contains* at the time of review — not just what it contained when first opened. A reviewer reading the description should not be surprised by the diff.

### Keeping Branches Up to Date

**Always merge master into a PR branch before reviewing or testing it.** A branch that has diverged from master may have hidden conflicts or stale code that makes the review misleading.

```bash
git fetch origin
git checkout <branch-name>
git merge origin/master   # Bring branch up to date

# If conflicts arise: resolve, stage, commit, push
git add <resolved-files>
git commit -m "Merge master into <branch-name> to resolve conflicts"
git push origin <branch-name>
```

**Rules:**
- Resolve conflicts in favour of the PR's intent — do not silently drop changes
- Push the merge commit back to origin so CI runs against the updated state
- If you resolve conflicts on someone else's PR, leave a comment explaining what was resolved

---

### Code Review Checklist

Before marking PR ready for review, ensure:

- [ ] All changes on single feature branch
- [ ] Branch created from master (not other branches)
- [ ] All existing tests pass locally
- [ ] New tests added for new functionality
- [ ] No unrelated changes included
- [ ] Commit messages follow format
- [ ] PR description complete and clear
- [ ] No "WIP" or "TODO" comments in code
- [ ] No console.log, var_dump, or debug code left
- [ ] Code follows project style/standards

---

## Pre-commit Validation Checklist

**CRITICAL:** Before committing, verify all items:

### Code Quality
- [ ] PHP syntax validation passed (`npm run build:php`)
- [ ] **Biome lint passed (`npm run lint`) — also enforced by `.githooks/pre-push`**
- [ ] Code follows project standards (read nearby files)

### Database & ORM
- [ ] Propel ORM used for all DB operations (no raw SQL)
- [ ] No `RunQuery()` calls in new code
- [ ] Dynamic IDs cast to `(int)`
- [ ] `=== null` checks, not `empty()` for objects
- [ ] Access object properties with `$obj->prop`, never `$obj['prop']`

### Asset Paths
- [ ] All CSS/image references use `SystemURLs::getRootPath()`
- [ ] No hardcoded `/skin/v2/` or relative paths

### Business Logic
- [ ] Service classes used for logic (in `src/ChurchCRM/Service/`)
- [ ] No business logic in controllers/routes
- [ ] No queries in templates
- [ ] Type casting applied to dynamic values

### File Inclusion
- [ ] Critical files use `require` (Header.php, Footer.php)
- [ ] Optional content uses `include` (plugins, supplementary)

### Frontend & UI
- [ ] Deprecated HTML attributes replaced with CSS
- [ ] Tabler + Bootstrap 5 classes used (NOT Bootstrap 4 / AdminLTE)
  - ✅ Correct: `col-md-6`, `w-100`, `d-flex`, `mt-3`, `fw-bold`, `gap-4`, `btn-ghost-secondary`
  - ❌ Wrong: `form-group`, `badge-success`, `ml-*`, `mr-*`, `btn-outline-secondary` for action menus
- [ ] All UI text wrapped with `i18next.t()` (JS) or `gettext()` (PHP)
- [ ] No `alert()` calls - use `window.CRM.notify()` instead

### Security & Input Validation
- [ ] `InputUtils` used for HTML escaping (not `htmlspecialchars` directly)
  - Use `escapeHTML()` for body content
  - Use `escapeAttribute()` for HTML attributes
  - Use `sanitizeText()` for plain text
  - Use `sanitizeHTML()` for rich text (Quill)
- [ ] `RedirectUtils` used for all redirects (not `header()`)
  - Use `redirect()` for relative URLs
  - Use `securityRedirect()` for access denied
  - Use `absoluteRedirect()` for absolute URLs
- [ ] `SlimUtils::renderErrorJSON()` for API errors (not exceptions)
- [ ] TLS verification enabled by default for HTTPS requests

### i18n & Translations
- [ ] **If new `gettext()` strings added**: Run `npm run locale:build`
- [ ] **If new `i18next.t()` strings added**: Run `npm run locale:build`
- [ ] After locale:build, run `npm run build` to regenerate assets
- [ ] Commit the updated `locale/terms/messages.po` file
- [ ] Use canonical UI terms (check for existing similar strings)

### Testing
- [ ] Tests pass locally before committing
- [ ] New functionality has tests (if applicable)
- [ ] Relevant Cypress tests run successfully
  - Clear logs before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
  - Run tests: `npx cypress run --e2e --spec "path/to/test.spec.js"`
  - Review logs for hidden errors: `cat src/logs/$(date +%Y-%m-%d)-php.log`
- [ ] No skipped tests (`.skip` or `.only`)
- [ ] No console errors or warnings in tests

### Git & Commits
- [ ] Commit message follows format (imperative, < 72 chars)
- [ ] Branch name follows kebab-case format
- [ ] No debugging code left (`console.log`, `var_dump`, `dd()`)
- [ ] No commented-out code blocks
- [ ] No TODO/FIXME comments (remove or create issue)
- [ ] One issue per branch
- [ ] Logs cleared: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

### Documentation
- [ ] Complex logic has inline comments
- [ ] Public methods/classes documented with PHPDoc
- [ ] Breaking changes documented in commit message
- [ ] New config options documented in README or comments

---

## Agent-Specific Behaviors

### Deleting a File <!-- learned: 2026-03-15 -->

Before deleting any file, always search these locations for references:

```bash
# 1. Source code references (links, requires, includes, route registrations)
grep -r "FileName.php" src/ --include="*.php" --include="*.js" --include="*.ts" -l

# 2. Cypress / test specs
grep -r "FileName" cypress/ -l

# 3. Docs site
grep -r "FileName" /path/to/docs.churchcrm.io -l

# 4. Wiki
grep -r "FileName" /path/to/wiki -l
```

Remove every reference found before (or as part of) the deletion commit:
- **Templates / views** — remove buttons, links, menu items
- **Test specs** — remove the `it()` block that visits/tests the page
- **Docs & wiki** — remove or update any page that describes the feature

❌ Don't delete the file and leave dead links, broken tests, or stale docs behind.

---

### Mandatory Pre-Commit Sequence <!-- learned: 2026-03-03 -->

**NEVER commit or push without completing ALL steps in order.**

```
1. Make the changes
2. npm run lint                ← Biome lint (catches what CI catches)
3. Build — use fastest option:
   - JS/CSS only  → npm run build:webpack   (fast)
   - PHP only     → npm run build:php
   - Mixed/all    → npm run build
4. Fix any errors
5. git diff                    ← Show the full diff to the user
6. Ask for approval            ← "Build passed. Please review. Shall I commit?"
7. Wait for explicit yes       ← "yes" / "lgtm" / "commit it" / "go ahead"
8. git add → git commit → git push
```

### Mandatory Pre-Push Biome Check <!-- learned: 2026-04-09 -->

**Biome lint MUST pass before any `git push`.** This is enforced two ways
so neither humans nor agents can ship code that fails CI lint:

1. **Git hook** — `.githooks/pre-push` runs `npm run lint` automatically.
   The hooks path is wired up by the `prepare` script in `package.json`
   (`git config core.hooksPath .githooks`), so `npm install` enables it
   for every clone. The hook is a no-op inside CI (`$CI` / `$GITHUB_ACTIONS`)
   because the lint job runs there as a separate step.

2. **Agent checklist** — agents must run `npm run lint` themselves before
   even *asking* for push approval. Do not rely on the hook to catch
   failures; surface them in the conversation so the user sees them.

**Bypass policy:**

```bash
# ❌ NEVER (silent bypass — hides failing lint from reviewer)
git push --no-verify

# ✅ Only acceptable when:
#    - The user explicitly authorizes it for an emergency hot-fix, AND
#    - The PR description calls out exactly which rule was bypassed and why
git push --no-verify   # bypassing lint per user approval — see PR body
```

If `npm run lint` ever times out or fails for an unrelated reason
(e.g. missing `node_modules`), fix the root cause — never paper over
it by removing the hook or bypassing.

**Why this is hardcoded:** Lint failures used to land on master because
contributors pushed before CI feedback arrived. The pre-push hook closes
that loop locally so feedback is instant and the master branch stays green.

**Examples:**

```
❌ WRONG — commits without building or showing diff
I've fixed the bug. [runs git commit]

❌ WRONG — asks to commit without running build first
Changes look good. Ready to commit — shall I proceed?

✅ CORRECT
npm run lint  → 0 errors
npm run build → Build successful

Here is the diff:
[git diff output]

Build and lint passed. Please review the changes above. Shall I commit with:
"fix: ..."?
```

**Explicit approval:** "yes", "looks good", "lgtm", "commit it", "go ahead", "ship it"

**Not approval:** silence, a follow-up question, or continuing the conversation.

**No exceptions** — not even for "small" or "obvious" changes.

### When User Asks to Commit

Even when the user says "commit it" — still run build + lint first if not done yet, then show the diff:

```bash
# 1. Validate
npm run lint
npm run build

# 2. Show diff
git diff

# 3. After user approves:
git add <specific files>
git commit -m "Fix issue #1234: Replace deprecated HTML with CSS"
git push origin fix/issue-1234-description
```

**Commit message should:**
- Reference the issue number
- Use imperative mood
- Describe what changed and why (not just file names)

### Pull Request Creation

When creating a PR:

1. **Let the user initialize** - Don't create PR without asking
2. **Provide description in code block** - Format as specified above
3. **Include all commits** - List the commits that will be in the PR
4. **Confirm before merging** - Get user approval before merge

---

## Pre-Commit Examples

### Example 1: Adding i18n Strings

```bash
# ❌ INCOMPLETE - Forgot locale rebuild
echo 'gettext("New Setting")' >> src/admin/views/settings.php
git add .
git commit -m "Add new setting to admin page"

# ✅ CORRECT - Rebuild locale before commit
echo 'gettext("New Admin Setting")' >> src/admin/views/settings.php
npm run locale:build      # Extract new strings
npm run build             # Regenerate assets
git add locale/terms/messages.po
git add src/admin/views/settings.php
git commit -m "Add new admin setting to UI"
```

### Example 2: Database Query

```bash
# ❌ WRONG - Uses raw SQL
$users = RunQuery("SELECT * FROM user_usr WHERE usr_role = 'admin'");

# ✅ CORRECT - Uses Perpl ORM
$users = UserQuery::create()
    ->filterByRole('admin')
    ->find();
```

### Example 3: HTML Escaping

```bash
# ❌ WRONG - No escaping
<?= $userData ?>

# ✅ CORRECT - Properly escaped
<?= InputUtils::escapeHTML($userData) ?>

# ✅ CORRECT - In form attribute
<input value="<?= InputUtils::escapeAttribute($address) ?>">
```

### Example 4: Bootstrap Classes

```bash
# ❌ WRONG - Bootstrap 4 / AdminLTE classes
<div class="w-100 pr-2 d-flex font-weight-bold ml-2">

# ✅ CORRECT - Tabler + Bootstrap 5 classes
<div class="w-100 ps-2 d-flex fw-bold ms-2">
```

---

## Branch Consolidation — Merging Multiple Feature Branches <!-- learned: 2026-04-07 -->

When consolidating multiple feature branches into one:

1. **Audit each branch** before merging:
   - `git log master..branch --oneline` — identify unique commits per branch
   - `git diff master...branch --name-only` — find overlapping files
   - `git branch --merged master` — identify already-merged branches (safe to delete)

2. **Merge in order of increasing conflict risk** (clean merges first)

3. **Conflict resolution patterns:**
   - Modify/delete conflicts where the file was refactored away in master — accept the deletion
   - Import conflicts from merges — keep all imports from both sides
   - Overlapping edits to the same file — manually review and combine intent from both branches

```bash
# Example: consolidating branch-a and branch-b into branch-combined
git checkout -b branch-combined master
git merge branch-a            # clean merge first
git merge branch-b            # higher-conflict merge second
# Resolve any conflicts, then:
git add <resolved-files>
git commit -m "Merge branch-b into branch-combined"
```

---

## Troubleshooting

### Commit Message Mistakes

**Scenario**: Created commit with wrong message

```bash
# Amend last commit (before push)
git commit --amend -m "Fix issue #1234: Correct message"
git push origin fix/issue-1234-description --force-with-lease
```

### Accidental File Commits

**Scenario**: Committed `node_modules/` or sensitive file

```bash
# Remove from history (before push)
git rm --cached node_modules
echo "node_modules/" >> .gitignore
git commit --amend
```

### Wrong Branch

**Scenario**: Made changes on master instead of feature branch

```bash
# Create new branch from current point
git branch fix/issue-1234-description
git reset --hard origin/master
git checkout fix/issue-1234-description
# Now on correct branch with changes
```

### Tests Failed After Commit

**Scenario**: Tests pass locally but fail in CI

```bash
# Clear logs and re-run locally
rm -f src/logs/$(date +%Y-%m-%d)-*.log
npm run test -- --spec "cypress/e2e/ui/path/to/test.spec.js"

# Check logs
cat src/logs/$(date +%Y-%m-%d)-php.log | tail -50

# Make fixes, amend commit
git add .
git commit --amend --no-edit
git push origin fix/issue-1234-description --force-with-lease
```

---

## Dependabot Workflow <!-- learned: 2026-04-21 -->

The repo uses grouped Dependabot updates configured in [.github/dependabot.yml](../../../.github/dependabot.yml). When reviewing or maintaining these PRs:

### Pinning away from a specific version

When a published release has regressions we don't want, add a scoped `ignore:` under the npm `updates` entry — **never** rely on `@dependabot ignore` PR comments alone. Comments live in Dependabot's internal state and can be lost when the config is rewritten; the YAML is durable.

```yaml
# .github/dependabot.yml
- package-ecosystem: "npm"
  directory: "/"
  open-pull-requests-limit: 5
  ignore:
    # Pinned: quill 2.0.3 has regressions we don't want to pick up.
    # Remove this entry when a newer 2.x release addresses them.
    - dependency-name: "quill"
      versions: ["2.0.3"]    # blocks just 2.0.3 — 2.0.4+ still proposed
```

Variants: `update-types: ["version-update:semver-patch"]` to block *all* patches of a package, or a bare `dependency-name` with no `versions`/`update-types` to pin the package entirely.

### Detecting deprecated `@types/*` stubs

Many libraries (e.g. `dompurify@3.x`) now ship their own `.d.ts` via the `types`/`exports` fields in their own `package.json`, which makes the `@types/*` companion obsolete. `npm` marks these stubs deprecated in `package-lock.json`:

```jsonc
"node_modules/@types/dompurify": {
  "deprecated": "This is a stub types definition. dompurify provides its own type definitions, so you do not need this installed."
}
```

**Action on a Dependabot `@types/*` bump:** before merging, grep `package-lock.json` for `"deprecated":` on that entry. If it's a stub, open a follow-up PR that **removes** the stub from `package.json` instead of bumping it. Verify by checking the real package's `node_modules/<pkg>/package.json` for a `types` or `exports.types` field.

### Concurrent Dependabot PR conflicts

Dependabot groups touch overlapping regions of `package.json` / `package-lock.json`, so once one group PR merges, sibling PRs (or follow-up branches cut from the pre-merge master) hit merge conflicts on those two files. Recipe to resolve:

```bash
git checkout <your-branch>
git fetch origin master
git merge origin/master                                 # conflict in package.json / package-lock.json
git checkout --theirs package.json package-lock.json    # take master's full state
# Re-apply your surgical edit (e.g. delete a single line)
npm install --no-audit --no-fund                        # regenerate lockfile cleanly
git add package.json package-lock.json
git commit                                              # merge commit
```

Do not hand-edit the lockfile merge markers — `npm install` is the source of truth. Running `npm run lint` + `npm run build:webpack` after resolution catches any missed constraint conflicts.

### Reviewing grouped `npm-minor-patch` PRs

Grouped bumps lump patch and minor updates into one PR. Scan-review approach:

1. **Sort by risk:** patches ≤ dev-deps < minors < majors. Dev-only deps (in `devDependencies` or used only in `locale/scripts/`, `scripts/`, etc.) are low-risk regardless of semver — they never ship to end users.
2. **Call out every minor explicitly:** the group label says "minor-patch" but a single minor bump in a UI library (e.g. `tom-select`) can change DOM behavior. Link its release notes in the review.
3. **Cross-check against MEMORY.md Critical Patterns:** many libraries here have workarounds documented (TomSelect `value=""` bug, `marked.parse()` XSS, etc.). A bump may invalidate or re-validate those workarounds.
4. **Green CI is the gate:** the UI Cypress suites (`test-root ui`, `test-subdir ui`) exercise TomSelect, DataTables, and calendar-event-editor directly. A fully-green run is sufficient sign-off for grouped patch/minor bumps.

### Dependabot PR state fields

```bash
gh pr view <n> --json mergeable,mergeStateStatus
```

- `mergeable: MERGEABLE` — **git** conflicts absent, says nothing about CI.
- `mergeStateStatus: UNSTABLE` — failing or pending required checks. A re-run may clear it.
- `mergeStateStatus: CLEAN` — all required checks pass; safe to merge.

Dependabot force-pushes its own branches frequently (rebase/re-resolve). If `git merge origin/master` against a Dependabot branch reports "Already up to date" when you expected conflicts, Dependabot likely just rebased it under you.

---

## Related Skills

- [Routing & Project Architecture](./routing-architecture.md) - Where to put code
- [PHP Best Practices](./php-best-practices.md) - Code standards
- [Security Best Practices](./security-best-practices.md) - Security validation before commit

---

Last updated: April 21, 2026
