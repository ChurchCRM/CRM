---
title: "Approved Plugin Registry"
intent: "Where the community-plugin allowlist lives and how to edit it"
tags: ["plugins","registry","community"]
prereqs: ["[[plugin-system]]", "[[hosted-remote-config]]"]
complexity: "beginner"
---

# Approved Plugin Registry

ChurchCRM installs community plugins only from an allowlist fetched at runtime.

| Layer | Path |
|-------|------|
| Live remote (every shipped install) | `https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json` |
| Constant | `CentralServices::PLUGIN_REGISTRY_URL` |
| Loader | `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` |
| Installer | `src/ChurchCRM/Plugin/PluginInstaller.php` |
| Runtime home | `src/plugins/community/{id}/` |
| Cypress fixture | `cypress/e2e/ui/plugins/community-plugin-lifecycle.spec.js` installs `hello-world` by id |

`ApprovedPluginRegistry` required keys: `id`, `name`, `version`, `downloadUrl` (HTTPS), `sha256` (64 hex), `risk` (`low`\|`medium`\|`high`), `riskSummary`. Optional: `permissions` (must be in `KNOWN_PERMISSIONS`), `minimumCRMVersion`, `author`, `homepage`, `reviewedAt`, `notes`. The loader ignores `schemaVersion` and `_comment`.

## How to add or bump a plugin

Open a PR with base `External` and edit the root `approved-plugins.json`.

Keep `hello-world` first: the Cypress lifecycle spec clicks `[data-plugin-id="hello-world"]` (retarget the spec in the same change to reorder).

Review checklist: [`plugin-security-scan.md`](./plugin-security-scan.md).

## Rules

- `External` and `Notifications` are live hosts for every install (`hosted-remote-config.md`).
- Community plugins are current product: the URL installer and Browse Approved UI ship today.
- Only allowlisted plugins install.
- Plugin copy sticks to what the plugin does; hosting, payment processing and install counts are out of scope.

## Core plugins (shipped in the zip)

Current list: `ls src/plugins/core/`. The one curated table (with per-plugin
routes/views) lives in `plugin-development.md` → "Core Plugins Reference" —
update that table, not a second copy here, when you add a core plugin.

## Related

- [`hosted-remote-config.md`](./hosted-remote-config.md)
- [`plugin-system.md`](./plugin-system.md)
- [`plugin-create.md`](./plugin-create.md)
- [`plugin-security-scan.md`](./plugin-security-scan.md)
- [`plugin-compliance.md`](./plugin-compliance.md)
