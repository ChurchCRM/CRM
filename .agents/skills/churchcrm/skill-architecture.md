---
title: Skill architecture
intent: How ChurchCRM skills stay small. Load this only when adding or editing a skill.
---

# Skill architecture

One rule lives in one file. Other files link. Do not copy.

- Router: `SKILL.md`
- Card: one topic, keep it short
- Command: `.claude/commands/*.md` is a pointer
- Evidence: `src/`, `package.json`, `src/locale/locales.json`, `DEVELOPING.md`
- History: git, not the hot path

`locale:build` → `.github/workflows/locale-generate-terms.yml` (merge to `master`).
UI stack → `package.json`.

If two cards disagree, shorten the long one.
