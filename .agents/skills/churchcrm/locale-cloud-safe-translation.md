# Cloud-Safe Locale Translation Workflow <!-- learned: 2026-04-01 -->

## Problem Solved

Cloud-based AI sessions can timeout mid-translation, causing **lost work**. The previous workflow had no backup — if a session crashed at locale 20 of 39, all 20 translations had to be redone.

## Solution

The updated `/locale-translate` workflow now:

1. **Creates a versioned branch** — `locale/{VERSION}-{DATE}` (e.g., `locale/7.1.0-2026-04-01`)
2. **Commits after each locale** — translations are secure locally
3. **Pushes after every commit** — work is immediately on remote (can't be lost to timeout)
4. **Supports resume** — rerun the command and it skips already-translated locales

---

## Usage

### Translate All Locales (Cloud-Safe)

```bash
/locale-translate --all --version 7.1.0
```

**What happens:**
1. ✅ Creates branch `locale/7.1.0-2026-04-01`
2. ✅ Translates locale 1 → commits → pushes
3. ✅ Translates locale 2 → commits → pushes
4. ✅ ... repeats for all 39 locales
5. ✅ Branch on remote has all work

### If Cloud Session Times Out

Simply **rerun the command**:
```bash
/locale-translate --all --version 7.1.0
```

**What happens:**
1. Detects you're already on `locale/7.1.0-2026-04-01`
2. Checks which locales were already pushed
3. **Resumes from the next untranslated locale**
4. No lost work, no redoing translations

---

## Behind the Scenes

The skill uses two new helper scripts:

### `locale-translate.js` (unchanged)
- Reads untranslated terms from batch files
- Applies Claude translations

### `locale-branch-manager.js` (NEW)
- Manages branch lifecycle
- Handles commit + push after each locale
- Detects resume scenarios
- Tracks which locales are already translated

```javascript
// Initialize branch
node locale/scripts/locale-branch-manager.js --init --version 7.1.0
// → creates/checks out locale/7.1.0-2026-04-01

// Commit and push one locale
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale fr --language "French - France" --terms 154
// → commits "locale: translate fr (...)" and pushes

// Get already-translated locales (for resume)
node locale/scripts/locale-branch-manager.js --get-translated
// → ["af", "sq", "am", "ar", ...]
```

---

## Complete Workflow: Translation → Upload → Download

```
Phase 1: TRANSLATE (Cloud-Safe Branch)
├─ /locale-translate --all --version 7.1.0
├─ Creates locale/7.1.0-2026-04-01 branch
├─ For each locale: translate → commit → push
└─ Result: Branch on remote with all translations

Phase 2: UPLOAD
├─ npm run locale:upload:missing
├─ Validates and shows samples
├─ Uploads to POEditor for human review
└─ Result: Translations in POEditor (pending approval)

Phase 3: DOWNLOAD (Automated)
├─ GH Action runs locale:download on schedule
├─ Fetches approved translations from POEditor
├─ Creates PR with src/locale/i18n/ updates
└─ Result: Approved translations merged to main
```

---

## Data Loss Prevention Features

| Scenario | Before | After |
|----------|--------|-------|
| **Cloud timeout at locale 20** | Lost all 20 translations | All 20 on remote branch, can resume |
| **Local crash** | Lost uncommitted work | All work was pushed, just checkout branch |
| **Forgot to commit one locale** | Manual fix needed | Can see exactly which ones are on remote |
| **Want to inspect work** | No branch history | View full branch: `git log locale/7.1.0-*` |

---

## Branch Naming Convention

Branches follow the pattern: `locale/{VERSION}-{YYYY-MM-DD}`

Examples:
- `locale/7.1.0-2026-04-01` — 7.1.0 release, started April 1
- `locale/7.2.0-2026-05-15` — 7.2.0 release, started May 15

**Why this format:**
- **Version** — Know which release the translations are for
- **Date** — Easy to clean up old branches, see when work started
- **Prefix** — Groups all locale branches together

---

## Resume Scenarios

### Scenario 1: Cloud times out during translation

```bash
# Session A
$ /locale-translate --all --version 7.1.0
# Translates: af, sq, am, ar, zh-CN, ... (cloud times out at locale 10)
# Branch has: locale/7.1.0-2026-04-01 with 10 commits on remote ✅

# Session B (new Claude instance)
$ /locale-translate --all --version 7.1.0
# Detects: already on locale/7.1.0-2026-04-01
# Checks: af, sq, am, ar, zh-CN on remote ✅
# Resumes from: zh-TW (next untranslated)
# Continues: zh-TW, cs, nl, et, fi, ...
```

### Scenario 2: Interrupted mid-command, rerun

```bash
# Command 1
$ /locale-translate --all --version 7.1.0
# Completes 15 locales before human kills task

# Command 2 (same session)
$ /locale-translate --all --version 7.1.0
# Detects: 15 locales already on current branch
# Resumes from: locale 16
# No duplicates, no lost work
```

---

## After Translations Are Complete

Once all 39 locales are translated and on the branch:

1. **Check branch status:**
   ```bash
   git log locale/7.1.0-* --oneline
   # Shows 39 commits: locale: translate af, sq, am, ... 
   ```

2. **Push (if not already):**
   ```bash
   git push origin locale/7.1.0-2026-04-01
   ```

3. **Upload to POEditor:**
   ```bash
   npm run locale:upload:missing
   # Validates batch files, shows samples, uploads
   ```

4. **Wait for POEditor approval** (human translators review)

5. **Download (automatic via GH Action)**
   - Action runs on schedule or manually triggered
   - Fetches approved translations
   - Creates PR with updates
   - Review and merge

6. **Cleanup (optional):**
   ```bash
   # After merge, delete the locale branch
   git branch -d locale/7.1.0-2026-04-01
   git push origin --delete locale/7.1.0-2026-04-01
   ```

---

## Key Files

- `.claude/commands/locale-translate.md` — Skill reference
- `locale/scripts/locale-branch-manager.js` — Branch management helper
- `locale/scripts/locale-translate.js` — File I/O helper
- `locale/scripts/poeditor-upload-missing.js` — Upload to POEditor (now simplified)

---

## FAQ

**Q: What if I run `/locale-translate --all` without a `--version`?**
- Error: `--version required`. You must specify the release version (e.g., 7.1.0).

**Q: Can I run translations on different cloud systems with the same version?**
- Not recommended — branch names include date, so `locale/7.1.0-2026-04-01` is tied to today. If restarting in a different session, ideally on the same day.

**Q: How do I know if translations were pushed?**
- Run: `git log origin/locale/7.1.0-* --oneline` — shows commits on remote

**Q: Can I merge the branch to main myself?**
- Not directly. The branch contains raw batch files. After POEditor approval and GH Action download, a separate PR is created with the approved translations (`src/locale/i18n/`). Merge that PR instead.

**Q: What happens to the locale branch after merge?**
- You can delete it. It's just a working branch. The important files are merged to main.

---

## Related Skills & Docs

- `.agents/skills/churchcrm/locale-ai-translation.md` — Church vocabulary rules
- `.agents/skills/churchcrm/locale-workflow-simplified.md` — Simplified upload/download workflow
- `.claude/commands/locale-translate.md` — Full skill reference
