<!-- staged: 2026-04-13 — destined for docs.churchcrm.io wiki/plugins/README.md -->

# Plugins

ChurchCRM supports WordPress-style plugins. A plugin adds functionality
— a new integration, a new settings page, a cron job, a hook that
reacts when a person is created — without editing core code.

There are two kinds of plugins:

- **Core plugins** ship with ChurchCRM and are maintained by the
  ChurchCRM team. They live at `src/plugins/core/` inside a ChurchCRM
  installation and include integrations like MailChimp, Vonage,
  Google Analytics, Gravatar, OpenLP, External Backup, and Custom
  Links.
- **Community plugins** are third-party extensions. They live at
  `src/plugins/community/` inside a ChurchCRM installation and are
  installed at runtime by an admin from the approved list.

All plugin management happens at **Admin → Plugins**.

---

## What plugins can do

Plugins can:

- Add new admin pages and menu items.
- Register routes under `/plugins/{id}/...`.
- Subscribe to people, family, event, group, financial, email, and
  system hooks.
- Inject HTML/JS/CSS into core pages.
- Store their own settings (sandboxed to `plugin.{id}.*` keys).
- Ship their own translations (see [Plugin Localization](./plugin-localization.md)).

Plugins **cannot** touch another plugin's settings, bypass the admin
auth middleware, write files outside their own directory, or ship
executable payloads like `.phar`, `.sh`, or `.exe`. ChurchCRM's
installer rejects archives that try.

---

## How to install a community plugin

See [Installing Community Plugins](./installing-community-plugins.md).
The short version: from **Admin → Plugins**, choose a plugin from the
approved list, click Install, review the risk summary, then click
Enable when you're ready.

---

## Security & compliance

Community plugins are a supply-chain surface — they run on your server
with the same permissions as the ChurchCRM application. Before you
install anything, read
[Plugin Security & Compliance](./plugin-security-and-compliance.md).
It covers the risk levels admins see in the install screen, the
capability tags approved plugins must declare, and the audit checklist
we recommend running every quarter.

---

## For plugin developers

If you want to **build** a community plugin, start in the ChurchCRM
repository's skill files rather than here — they have the canonical
technical details:

- [`plugin-create.md`](https://github.com/ChurchCRM/CRM/blob/main/.agents/skills/churchcrm/plugin-create.md)
  — the community plugin quickstart: scaffold, code, run the security
  scan against your own tree, build a reproducible zip, and open the
  `approved-plugins.json` pull request.
- [`plugin-development.md`](https://github.com/ChurchCRM/CRM/blob/main/.agents/skills/churchcrm/plugin-development.md)
  — the technical reference, including the full allowed/forbidden
  capability contract.
- [`plugin-security-scan.md`](https://github.com/ChurchCRM/CRM/blob/main/.agents/skills/churchcrm/plugin-security-scan.md)
  — the review checklist every approved community plugin must pass
  before it enters the registry.

> **Note:** ChurchCRM has a separate `plugin-migration.md` skill, but
> it applies **only** to the core plugins shipped in
> `src/plugins/core/`. Community plugin authors do not use it.

Once your plugin is tagged and hosted at an immutable HTTPS URL with a
reproducible SHA-256, open a pull request against
`src/plugins/approved-plugins.json` in the CRM repo. A ChurchCRM
maintainer will run the security-scan checklist against your zip and
either approve, request changes, or reject.
