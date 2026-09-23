---
title: Plugin System
intent: Runtime map. Building a plugin starts at plugin-development.md.
---

# Plugin System

Code: `src/ChurchCRM/Plugins/`, `src/plugins/`.
Author guide: `plugin-development.md` then `plugin-create.md`.
Core plugin API bump: `plugin-migration.md`.
Intake scan: `plugin-security-scan.md`.

## Registry

- Bundled list: `src/plugins/approved-plugins.json`
- Remote list: `https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/approved-plugins.json`
- Do not use the retired `External` branch URL

## Runtime

- Manager and hooks live under `src/ChurchCRM/` — read those classes, do not copy hook tables into this file
- Community plugins ship their own translations (`PluginLocalization`). They do not go through core `messages.po`
- Plugins are current product, not vision
