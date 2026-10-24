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
It points at `approved-plugins.json` on the `External` branch. A failed fetch yields an empty registry for the session (`hosted-remote-config.md`).
Loader: `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php`.

## Runtime

Read `PluginManager` and the hook classes. Community plugins ship their own translations.

## Menu items

`PluginManager::getPluginMenuItems()` feeds `Menu::addPluginMenuItems()`, the only consumer of a plugin's `getMenuItems()`. An item's optional `permission` is checked with `User::isEnabledSecurity()`; unknown names hide the item from non-admins, and no key shows it to everyone. It is visibility only: plugin routes still need `ChurchCRM\Slim\Middleware\Request\Auth\*` middleware. Details: `plugin-development.md`.
