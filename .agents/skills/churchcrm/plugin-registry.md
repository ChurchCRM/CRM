---
title: "Approved Plugin Registry"
intent: "Where the community-plugin allowlist lives, how agents must fetch it, and what not to invent"
tags: ["plugins","registry","community"]
prereqs: ["[[plugin-system]]", "[[hosted-remote-config]]"]
complexity: "beginner"
---

# Approved Plugin Registry

Use this file instead of guessing a GitHub branch or inventing a marketplace.

## Current product

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

Open a PR with **base `External`** and edit **root** `approved-plugins.json`. Do not put the live allowlist on master unless a separate fallback PR says so.

Keep **hello-world first** unless the Cypress lifecycle spec is retargeted in the same change. That spec clicks `[data-plugin-id="hello-world"]`.

Current allowlist (restored 2026-09-22 from External history / PR #8928):

1. `hello-world` 1.0.1 — ChurchCRM/community-plugin-hello-world, risk low, SHA `ad1bfa1427b778c052f4c816072c8d9bc2f79cfc07fe481d9dd29be4c448082a`
2. `meeting-outlines` 1.0.2 — huckjs-sys/meeting-outlines, risk high, SHA `494e6534861ea6a6a74435d0efcbdab7474bca72c54bb9d0d34e994e7f51a81f`

Review checklist: [`plugin-security-scan.md`](./plugin-security-scan.md).

## Do not do this

- Do not delete `External` or `Notifications`. A 21 Sep 2026 PR-less prune did that; the registry 404'd and UI shard 2 failed (#9969).
- Do not teach agents that `External` is retired. That was wrong after the restore. See [`hosted-remote-config.md`](./hosted-remote-config.md).
- Do not call community plugins "future". The URL installer and Browse Approved UI are current product.
- Do not add a plugin that is not in the allowlist.
- Do not invent SaaS hosting, payment processing, or install counts in plugin copy.

## Core plugins (shipped in the zip)

`src/plugins/core/`: `custom-links`, `external-backup`, `google-analytics`, `gravatar`, `holidays`, `mailchimp`, `openlp`, `vonage`.

If you add a core plugin, update this list and the tables in `plugin-system.md` / `plugin-development.md` / `plugin-migration.md` in the same PR.

## Related

- [`hosted-remote-config.md`](./hosted-remote-config.md)
- [`plugin-system.md`](./plugin-system.md)
- [`plugin-create.md`](./plugin-create.md)
- [`plugin-security-scan.md`](./plugin-security-scan.md)
- [`plugin-compliance.md`](./plugin-compliance.md)
