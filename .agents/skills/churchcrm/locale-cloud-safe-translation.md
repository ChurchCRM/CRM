# Cloud-Safe Locale Translation Workflow <!-- learned: 2026-04-01 -->

## Problem Solved

Cloud-based AI sessions can timeout mid-translation, causing **lost work**. The previous workflow had no backup — if a session crashed at locale 20 of 39, all 20 translations had to be redone.

## Solution (MANDATORY — all steps are NON-NEGOTIABLE) <!-- learned: 2026-04-09 -->

The `/locale-translate` workflow MUST:

1. **Create a versioned branch** — `locale/{VERSION}-{DATE}` — BEFORE any translation starts
2. **Commit after each locale** — translations are secure locally
3. **Push after every commit** — work is immediately on remote (can't be lost to timeout)
4. **Upload to POEditor after every push** — `node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`
5. **Support resume** — rerun the command and it skips already-translated locales

**None of these steps are optional.** We have lost hours of work from agents that skipped steps 2-4.

---

## Usage

### Translate All Locales (Cloud-Safe)

```bash
/locale-translate --all --version 7.1.0
```

**What happens:**
1. Creates branch `locale/7.1.0-2026-04-01`
2. Translates locale 1 → commits → pushes → uploads to POEditor
3. Translates locale 2 → commits → pushes → uploads to POEditor
4. ... repeats for all 39 locales
5. Branch on remote has all work, POEditor has all translations

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

## Complete Workflow: Translate+Upload → Download <!-- learned: 2026-04-09 -->

```
Phase 1: TRANSLATE + UPLOAD (Cloud-Safe Branch)
├─ /locale-translate --all --version 7.1.0
├─ Creates locale/7.1.0-2026-04-01 branch
├─ For each locale: translate → commit → push → upload to POEditor
└─ Result: Branch on remote AND POEditor both have all translations

Phase 2: DOWNLOAD (Automated)
├─ GH Action runs locale:download on schedule
├─ Fetches approved translations from POEditor
├─ Creates PR with src/locale/i18n/ updates
└─ Result: Approved translations merged to main
```

**Note:** Upload is now part of Phase 1, not a separate phase. Each locale is uploaded to POEditor immediately after it's committed and pushed. This eliminates the manual upload step and protects against data loss.

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

Once all locales are translated (each was committed, pushed, and uploaded per-locale):

1. **Check branch status:**
   ```bash
   git log locale/7.1.0-* --oneline
   # Shows N commits: locale: translate af, sq, am, ... 
   ```

2. **Verify all uploads reached POEditor:**
   ```bash
   node locale/scripts/poeditor-upload-missing.js --dry-run
   # Should show 0 terms to upload (all were uploaded per-locale)
   ```

3. **Wait for POEditor approval** (human translators review)

4. **Download (automatic via GH Action)**
   - Action runs on schedule or manually triggered
   - Fetches approved translations
   - Creates PR with updates
   - Review and merge

5. **Cleanup (optional):**
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
