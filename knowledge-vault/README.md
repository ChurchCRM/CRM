---
title: "ChurchCRM Knowledge Vault"
tags: ["documentation", "vault-guide"]
---

# ChurchCRM Knowledge Vault

An [Obsidian](https://obsidian.md) vault for browsing ChurchCRM's development docs with backlinks and a graph view.

**Open this folder (`knowledge-vault/`) as a vault in Obsidian** — that's the only setup step.

## What's in here

- [`skills/`](./skills/) — symlink to [`.agents/skills/`](../.agents/skills/), the actual skill files Claude Code reads. Start at [`skills/churchcrm/SKILL.md`](./skills/churchcrm/SKILL.md), the task-based index.
- [`CLAUDE.md`](./CLAUDE.md) — symlink to the repo's [`CLAUDE.md`](../CLAUDE.md).

Nothing here is a copy. Every note is a symlink into the real files, so editing a note in Obsidian edits the source file directly — there's no second copy to keep in sync, and Claude Code's context still comes from `.agents/skills/` exactly as before.

## What this is (and isn't) for

- **Is for:** human browsing — graph view, backlinks, quick navigation across the ~50 skill files without opening each one in an editor.
- **Isn't:** a change to how Claude Code loads context. The task-based lookup table in `CLAUDE.md` and `skills/churchcrm/SKILL.md` already ensures a session only reads the skills relevant to its task — that's what keeps sessions token-efficient, and this vault doesn't alter it.

## Adding new notes

Don't add new `.md` files directly under `knowledge-vault/` — add them to `.agents/skills/churchcrm/` (or the repo root, for things like `CLAUDE.md`) so they stay under version control as the source of truth, and they'll show up here automatically through the symlink.
