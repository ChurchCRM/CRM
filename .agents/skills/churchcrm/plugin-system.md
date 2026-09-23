---
title: Plugin System
intent: Runtime map. Building a plugin starts at plugin-development.md.
---

# Plugin System

Code: `src/ChurchCRM/Plugins/`, `src/plugins/`.
Author guide: `plugin-development.md` then `plugin-create.md`.
Registry detail: `plugin-registry.md`.

## Registry

Live URL is `CentralServices::PLUGIN_REGISTRY_URL` in `src/ChurchCRM/Remote/CentralServices.php`.
Today that is the **External** branch:
`https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json`

Bundled copy: `src/plugins/approved-plugins.json`.
Loader: `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php`.
Do not invent a different branch. If the constant changes, this card follows the constant.

## Runtime

Read `PluginManager` and the hook classes. Community plugins ship their own translations. Plugins are current product.
