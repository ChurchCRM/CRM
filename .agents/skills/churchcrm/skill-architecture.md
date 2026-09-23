---
title: Skill architecture
intent: How ChurchCRM skills stay small. Load this only when adding or editing a skill.
---

# Skill architecture

One rule lives in one file. Other files link. Do not copy.

- Router: `SKILL.md` (when to open a card)
- Card: one topic, under ~4 KB when you can
- Command: `.claude/commands/*.md` is a pointer only
- Evidence: cite a path in `src/`, `package.json`, or `locale/locales.json` — do not paste catalogs
- History: git. Do not keep `learned:` essays on the hot path

Locale count: `locale/locales.json`. UI stack: `package.json` (`bootstrap`, `@tabler/core`).
`locale:build` runs on merge to `master` (`.github/workflows/locale-generate-terms.yml`). Feature PRs wrap `gettext()` / `i18next.t()` only.

If two cards disagree, shorten the long one. Do not add a third copy.
