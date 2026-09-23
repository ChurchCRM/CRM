---
title: "Approved Plugin Registry"
intent: "Where the community-plugin allowlist lives, how agents must fetch it, and what not to invent"
tags: ["plugins","registry","community"]
prereqs: ["[[plugin-system]]"]
complexity: "beginner"
---

# Approved Plugin Registry

Use this file instead of guessing a GitHub branch or inventing a marketplace.

## Current product

ChurchCRM installs community plugins only from an allowlist.

| Layer | Path |
|-------|------|
| Bundled fallback (always ship this) | `src/plugins/approved-plugins.json` |
| Remote refresh | `https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/approved-plugins.json` |
| Loader | `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` |
| Installer | `src/ChurchCRM/Plugin/PluginInstaller.php` |
| Runtime home | `src/plugins/community/{id}/` |

PRs that approve a plugin edit the bundled JSON in this repo. After merge, publish the same file on the `Notifications` branch so running churches can refresh without waiting for a CRM upgrade.

## Do not do this

- Do not fetch `https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json`. That path 404s and broke CI (#9969).
- Do not call community plugins "future". The URL installer and Browse Approved UI are current product.
- Do not add a plugin that is not in the allowlist.
- Do not invent SaaS hosting, payment processing, or install counts in plugin copy.

## Core plugins (shipped in the zip)

`src/plugins/core/`: `custom-links`, `external-backup`, `google-analytics`, `gravatar`, `holidays`, `mailchimp`, `openlp`, `vonage`.

If you add a core plugin, update this list and the tables in `plugin-system.md` / `plugin-development.md` / `plugin-migration.md` in the same PR.

## Related

- [`plugin-system.md`](./plugin-system.md)
- [`plugin-create.md`](./plugin-create.md)
- [`plugin-security-scan.md`](./plugin-security-scan.md)
- [`plugin-compliance.md`](./plugin-compliance.md)
