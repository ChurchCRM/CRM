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

**Test before adding a fact to a card: could one `grep`/read of the source answer this as reliably?** If yes, it's a catalog — point at the file/pattern instead of transcribing it. Two staleness audits in this repo's history caught exactly this failure mode costing real review time: a pinned dependency-version table that drifted from `package.json`, a transcribed `webpack.config.js` entry list, and function-signature tables that just repeated a short file's own exports. None of those needed a human or a debugging session to discover — they were always one file read away, so the card added token cost and a staleness liability for zero judgment. A version number, a line number, a file count, or a full list of routes/entries/functions is not a durable rule; a *pattern* to follow (one short illustrative snippet) is fine, a *catalog* of everything that currently exists is not.

`locale:build` → `.github/workflows/locale-generate-terms.yml` (merge to `master`).
UI stack → `package.json`.
Plugin registry URL → `CentralServices::PLUGIN_REGISTRY_URL`.
