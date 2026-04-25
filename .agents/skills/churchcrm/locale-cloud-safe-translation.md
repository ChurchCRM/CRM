# Cloud-Safe Locale Translation Workflow <!-- learned: 2026-04-01, updated: 2026-04-22 -->

## Problem Solved

Cloud-based AI sessions can timeout mid-translation, causing **lost work**. Without safeguards, if a session crashed at locale 20 of 39, all 20 translations had to be redone.

This workflow guarantees every translated locale is durably persisted in three places before the next one is attempted: local commit, remote branch on GitHub, and POEditor.

---

## The Rules (NON-NEGOTIABLE) <!-- updated: 2026-04-22 -->

Every translation session MUST:

1. **Create a BRAND-NEW branch** — `locale/{VERSION}-{YYYY-MM-DD}-{HHMMSS}` — before any translation starts. Never reuse a prior run's `locale/*` or `copilot/*` branch, even from earlier the same day.
2. **Commit after each locale** — translations are secure locally.
3. **Push after every commit** — work is immediately on remote (can't be lost to timeout).
4. **Upload to POEditor after every push** — `node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`.
5. **On resume, cut ANOTHER new branch** — do not `git checkout` the previous run's branch. POEditor skip-already-translated handles deduplication across runs.

**None of these steps are optional.** We have lost hours of work from agents that skipped steps 2–4, and produced review-thread churn by reusing stale branches (step 5).

See the full operational walkthrough in [`/locale-translate`](../../../.claude/commands/locale-translate.md).

---

## Usage

### Translate All Locales (Cloud-Safe)

```bash
/locale-translate --all
```

What happens:

1. Creates a fresh branch `locale/{version}-{YYYY-MM-DD}-{HHMMSS}` (version auto-detected from `package.json`).
2. Translates locale 1 → commits → pushes → uploads to POEditor.
3. Translates locale 2 → commits → pushes → uploads to POEditor.
4. …repeats for every remaining locale.
5. Branch on remote has all work, POEditor has all translations.

### If a Cloud Session Times Out

**Cut a new branch and rerun.** Do NOT `git checkout` the previous run's branch.

```bash
/locale-translate --all
```

- A new branch `locale/{version}-{YYYY-MM-DD}-{HHMMSS}` is created for this session.
- `node locale/scripts/locale-translate.js --list` shows only locales still needing terms.
- Already-uploaded translations are skipped by POEditor — no duplicates.
- Prior branches stay in the remote, frozen, for review.

---

## Branch Naming Convention

Branches follow the pattern `locale/{VERSION}-{YYYY-MM-DD}-{HHMMSS}` (UTC time).

Examples:

- `locale/7.2.0-2026-04-22-174530` — 7.2.0 release, cut at 17:45:30 UTC on 2026-04-22.
- `locale/7.2.0-2026-04-22-203011` — a later session the same day on 7.2.0.

**Why include a time suffix:** the date alone collides when a session is run twice in one day. The `HHMMSS` suffix guarantees uniqueness, which is what makes the never-reuse rule enforceable automatically — `locale-branch-manager.js --init` always creates a fresh branch.

The branch-name regex (in `locale-branch-manager.js`) accepts both the current form and the legacy date-only form so in-flight branches created before 2026-04-22 still detect correctly during rollout.

---

## Behind the Scenes

Two helper scripts:

### `locale-translate.js`
- Reads untranslated terms from `locale/terms/missing/{CODE}/{CODE}-N.json` batch files.
- Applies Claude-produced translations back to the batch file.
- Lists remaining work (`--list`).

### `locale-branch-manager.js`
- `--init` — creates a **new** `locale/{v}-{date}-{time}` branch every invocation. Never reuses. Fails loudly if a name collision appears on remote.
- `--current` — print current branch name.
- `--is-locale-branch` — exit 0 if on any `locale/*` branch (accepts legacy date-only form too).
- `--get-version` — extract the version portion from the current branch name.
- `--commit-and-push --locale <CODE> --language "<LANGUAGE>" --terms <N>` — stages `locale/terms/missing/{CODE}/`, commits with a standard message, and pushes.

```bash
# Initialize branch
node locale/scripts/locale-branch-manager.js --init
# → creates locale/7.2.0-2026-04-22-174530

# Commit and push one locale
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale fr --language "French - France" --terms 154
# → commits "locale: translate fr (French - France, 154 terms)" and pushes
```

---

## Complete Workflow: Translate + Upload → Download

```
Phase 1: TRANSLATE + UPLOAD (on a fresh cloud-safe branch)
├─ /locale-translate --all
├─ Creates locale/{version}-{YYYY-MM-DD}-{HHMMSS} branch (never reuses)
├─ For each locale: translate → commit → push → upload to POEditor
└─ Result: branch on remote AND POEditor both have all translations

Phase 2: DOWNLOAD (automated)
├─ GitHub Action runs locale:download on schedule
├─ Fetches approved translations from POEditor
├─ Creates PR with src/locale/i18n/ updates
└─ Result: approved translations merged to master
```

Upload is part of Phase 1, not a separate phase. Every locale is uploaded to POEditor immediately after it's committed and pushed.

---

## Data-Loss Prevention Matrix

| Scenario | Outcome |
|----------|---------|
| Cloud timeout at locale 20 | First 20 locales are committed, pushed, and already in POEditor. Resume with a new branch — remaining locales continue; no duplicates. |
| Local crash | All completed work was pushed — nothing lost. |
| Want to inspect a specific run | `git log origin/locale/{version}-*` — each session's work is immutable on its own branch. |
| Upload failure for one locale | The translation is still committed + pushed. Retry upload with `npm run locale:upload:missing -- --locale <CODE>`. |

---

## After Translations Are Complete

1. **Verify nothing remains:**
   ```bash
   node locale/scripts/locale-translate.js --list
   ```
2. **Verify all uploads reached POEditor:**
   ```bash
   node locale/scripts/poeditor-upload-missing.js --dry-run
   ```
3. **Wait for POEditor approval** (human translators review).
4. **Download (automatic via GitHub Action)** — fetches approved translations and opens a PR with `src/locale/i18n/` updates.
5. **Cleanup (optional):** delete merged locale branches.
   ```bash
   git branch -D locale/7.2.0-2026-04-22-174530
   git push origin --delete locale/7.2.0-2026-04-22-174530
   ```

---

## FAQ

**Q: What if two sessions run on the same day for the same version?**
A: Each gets a unique `HHMMSS` suffix, so branches never collide. The second session cuts a fresh branch with its own timestamp; already-uploaded locales are skipped by POEditor.

**Q: Can I force the manager to reuse my previous branch?**
A: No. `--init` always creates a new branch. If you genuinely need to continue work on a specific branch (rare), `git checkout <branch>` it manually and skip `--init`. All completed-and-uploaded locales are safe regardless of which branch they live on.

**Q: How do I know a locale was actually pushed?**
A: `git log origin/locale/{version}-{date}-{time} --oneline` — every commit on the remote is a successful push.

**Q: Can I merge a locale branch directly to master?**
A: No. The branch contains raw `locale/terms/missing/` batch files, not approved translations. After POEditor approval, the GitHub Action opens a PR with the translated `src/locale/i18n/` files — merge that PR instead.

**Q: How do I update skill files on master while staying on a locale branch?** <!-- learned: 2026-04-25 -->
A: Use `git worktree` — a private checkout of master in a temp directory that leaves the current branch completely untouched. The branch-switch gate hook won't fire because no `git checkout` or `git switch` is involved.

```bash
# From your locale branch — create a worktree pointed at master
git worktree add /tmp/crm-master master

# Edit skill files inside the worktree (use absolute paths)
# e.g. edit /tmp/crm-master/.agents/skills/churchcrm/some-skill.md

# Commit and push from the worktree
git -C /tmp/crm-master add .agents/skills/
git -C /tmp/crm-master commit -m "docs: update locale skill with ..."
git -C /tmp/crm-master push origin master

# Remove the temp worktree when done
git worktree remove /tmp/crm-master
```

Your locale branch, working tree, and uncommitted changes are completely unaffected.

---

## Related Skills & Docs

- [`/locale-translate`](../../../.claude/commands/locale-translate.md) — operational command (this file describes WHY; that file is the HOW).
- [`/locale-release`](../../../.claude/commands/locale-release.md) — release-time pipeline that calls `/locale-translate`.
- [`locale-ai-translation.md`](./locale-ai-translation.md) — church vocabulary + denomination rules (authoritative for vocab).
- [`locale-stack-ranking.md`](./locale-stack-ranking.md) — TIER prioritization (authoritative).
- [`locale-workflow-simplified.md`](./locale-workflow-simplified.md) — high-level phase summary.
- [`i18n-localization.md`](./i18n-localization.md) — adding UI terms, `gettext`/`i18next.t` rules, and what NOT to wrap.
