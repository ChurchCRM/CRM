---
title: Skill architecture
intent: How ChurchCRM skills stay small. Load this only when adding or editing a skill.
---

# Skill architecture

One rule lives in one file. Other files link. Do not copy catalogs from `src/`.

- Router: `SKILL.md`
- Card: one topic, keep it short
- Command: `.claude/commands/*.md` is a pointer
- Evidence: `src/`, `package.json`, `src/locale/locales.json`, `DEVELOPING.md`
- Named headings that `CLAUDE.md` links to must keep those names

A durable gotcha that cannot be read from one source file stays on the card as a short rule (EditSelf exclusive, CSRF on a Slim group, plugin registry constant). Do not append `learned:` essays. If the rule changed, replace the sentence.

`locale:build` → `.github/workflows/locale-generate-terms.yml` (merge to `master`).
UI stack → `package.json`.
Plugin registry URL → `CentralServices::PLUGIN_REGISTRY_URL`.
