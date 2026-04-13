# ChurchCRM Plugin System

ChurchCRM features a WordPress-style plugin architecture that lets you
extend functionality without modifying core code. Plugins come in two
flavours:

- **Core plugins** (`src/plugins/core/`) ship with ChurchCRM and are
  maintained in this repository.
- **Community plugins** (`src/plugins/community/`) are third-party
  extensions installed at runtime via the admin-only URL installer,
  gated by an approved-plugins allowlist.

> **Read the security model first.** Community plugins are a supply-chain
> surface for your parish data. Before you install one, read
> [`.agents/skills/churchcrm/plugin-compliance.md`](../../.agents/skills/churchcrm/plugin-compliance.md).
> Before you write one, read
> [`.agents/skills/churchcrm/plugin-development.md`](../../.agents/skills/churchcrm/plugin-development.md)
> and then
> [`.agents/skills/churchcrm/plugin-security-scan.md`](../../.agents/skills/churchcrm/plugin-security-scan.md).

---

## Directory layout

```
src/plugins/
├── approved-plugins.json           # Allowlist for URL installs
├── index.php                       # Slim entry point (/plugins/...)
├── routes/                         # Admin management routes
│   ├── management.php
│   └── api/management.php
├── views/                          # Admin management UI
├── core/
│   ├── mailchimp/
│   │   ├── plugin.json
│   │   └── src/MailChimpPlugin.php
│   ├── vonage/
│   ├── google-analytics/
│   ├── openlp/
│   ├── gravatar/
│   ├── external-backup/
│   └── custom-links/
└── community/
    └── {plugin-id}/                # Installed at runtime, never in signatures.json
        ├── plugin.json
        ├── src/{PluginClass}.php
        ├── routes/routes.php       # optional
        ├── views/…                 # optional
        └── locale/                 # optional — see Localization below
            ├── textdomain/{locale}/LC_MESSAGES/{plugin-id}.mo
            └── i18n/{locale}.json
```

Community plugins are excluded from the orphan-scan in
`AppIntegrityService::isExcludedFromOrphanDetection()` and from
`scripts/generate-signatures-node.js`. This is enforced by the Cypress
regression test
`cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`.

---

## Installing a community plugin

Community plugins must be installed through the admin API. Raw disk
installs work but skip the safety checks, so prefer the URL installer:

```
GET  /plugins/api/approved                         # list vetted plugins
POST /plugins/api/plugins/install                  # { "downloadUrl": "https://…" }
```

Requirements enforced by `PluginInstaller`:

1. The URL must be in `src/plugins/approved-plugins.json`.
2. Your CRM version must meet `minimumCRMVersion`.
3. The destination `community/{id}` must not already exist.
4. The download is ≤ 20 MB, HTTPS, with TLS verification.
5. The downloaded bytes' SHA-256 must match the registry entry.
6. The zip must have no ZIP-Slip paths, no `..`, no absolute paths, no
   drive letters, no control bytes, no symlinks.
7. Exactly one top-level directory, named after the plugin `id`.
8. Only allowed extensions (`.php`, `.js`, `.json`, `.css`, `.html`,
   `.twig`, `.md`, `.png`/`.jpg`/`.svg`/…, `.woff`, `.po`/`.mo`, etc.).
   No `.phar`, `.sh`, `.exe`, `.so`, `.dll`.
9. Extracted `plugin.json` must match `id`, `version`,
   `type: "community"`.

The installer does **not** auto-enable the plugin — an admin must click
Enable after reviewing.

### Approved plugin entry

Every entry in `approved-plugins.json` declares:

| Field | Required | What it means |
|-------|:--------:|---------------|
| `id` | ✓ | Kebab-case id, must match `plugin.json` |
| `name` | ✓ | Display name |
| `version` | ✓ | Semver, must match `plugin.json` |
| `downloadUrl` | ✓ | HTTPS URL to an immutable release zip |
| `sha256` | ✓ | 64-hex SHA-256 of the zip bytes |
| `risk` | ✓ | `low` \| `medium` \| `high` |
| `riskSummary` | ✓ | One plain-language sentence admins see before Install |
| `permissions` | optional | Capability tags from `ApprovedPluginRegistry::KNOWN_PERMISSIONS` |
| `minimumCRMVersion` | optional | Enforced by the installer |
| `author`, `homepage`, `reviewedAt`, `notes` | optional | Metadata |

---

## Creating a plugin

See [`.agents/skills/churchcrm/plugin-development.md`](../../.agents/skills/churchcrm/plugin-development.md)
for the full walkthrough. High-level steps:

1. Create `src/plugins/{core|community}/{your-id}/plugin.json` with
   `type`, `mainClass`, settings, hooks, and optional routes.
2. Create the main class at
   `{plugin}/src/{YourClass}Plugin.php` extending `AbstractPlugin`.
3. Optionally add `routes/routes.php` (only loaded for active plugins)
   and `views/*.php` templates.
4. Optionally add `locale/textdomain/…` and `locale/i18n/…` for
   translations.
5. **Before submitting** a community plugin: run the static-analysis
   pass in `plugin-security-scan.md` against your own code.

---

## Plugin Localization (no POeditor)

Community plugins are **not** added to the ChurchCRM POeditor project.
Ship translations inside the plugin directory:

```
locale/
├── textdomain/              # PHP gettext (.mo) files
│   └── {locale}/LC_MESSAGES/{plugin-id}.mo
└── i18n/                    # JavaScript i18next (flat key/value JSON)
    └── {locale}.json
```

- PHP code uses `dgettext('{plugin-id}', 'Hello')` — never plain
  `gettext()`.
- JS code reads strings from `window.CRM.plugins['{plugin-id}'].i18n[key]`
  (or registers the map as an i18next namespace via `addResourceBundle`).
- Files larger than 512 KB are rejected by the loader.
- Only flat `key => string` maps are accepted; nested JSON is silently
  dropped.
- Missing locales fall back to `en_US.json`.

Full guide:
[`plugin-development.md → Plugin Localization`](../../.agents/skills/churchcrm/plugin-development.md#plugin-localization-independent-of-poeditor).

---

## Hooks system

ChurchCRM exposes WordPress-style hooks (`HookManager::addAction`,
`HookManager::addFilter`). See `src/ChurchCRM/Plugin/Hooks.php` for the
full constant list. Hook categories:

- **People / Family** — `PERSON_*`, `FAMILY_*`, `*_VIEW_TABS`
- **Financial** — `DONATION_RECEIVED`, `DEPOSIT_CLOSED`
- **Events / Groups** — `EVENT_*`, `GROUP_MEMBER_*`
- **Email / SMS** — `EMAIL_PRE_SEND`, `EMAIL_SENT`
- **UI** — `MENU_BUILDING`, `DASHBOARD_WIDGETS`, `SETTINGS_PANELS`, `ADMIN_PAGE`
- **System** — `SYSTEM_INIT`, `SYSTEM_UPGRADED`, `CRON_RUN`, `API_RESPONSE`

If a hook touches PII or financial data, declare the matching
capability tag (`hooks.person`, `hooks.financial`, …) in your approved
registry entry. See `plugin-development.md` for the full allow/deny
contract.

---

## Core plugins

| Plugin | Purpose |
|--------|---------|
| `mailchimp` | MailChimp email list integration |
| `vonage` | Vonage SMS notifications |
| `google-analytics` | Google Analytics tracking |
| `openlp` | OpenLP presentation software integration |
| `gravatar` | Gravatar profile photos |
| `external-backup` | Off-site backup uploads |
| `custom-links` | User-defined menu links |

---

## Admin UI & API reference

Plugins are managed from **Admin → Plugins**.

```
# Management UI (admin only)
GET  /plugins/management                    # List + enable/disable page

# Management API (admin only)
GET  /plugins/api/plugins                   # List all discovered plugins
GET  /plugins/api/plugins/{id}              # Plugin details
POST /plugins/api/plugins/{id}/enable       # Enable
POST /plugins/api/plugins/{id}/disable      # Disable
POST /plugins/api/plugins/{id}/settings     # Update settings
POST /plugins/api/plugins/{id}/test         # Test connection (hasTest: true)
POST /plugins/api/plugins/{id}/reset        # Clear all plugin settings
GET  /plugins/api/approved                  # Approved registry for URL installer
POST /plugins/api/plugins/install           # Install from an approved URL

# Public
GET  /plugins/status/{id}                   # Used by core UI to gate plugin tabs
```

---

## Questions?

For plugin development help, visit:

- ChurchCRM Chat: https://discord.gg/tuWyFzj3Nj
- Forum: https://forum.churchcrm.io
- Issues: https://github.com/ChurchCRM/CRM/issues
- Docs: https://docs.churchcrm.io

Plugin-specific skill files:

- [Plugin System](../../.agents/skills/churchcrm/plugin-system.md)
- [Plugin Development](../../.agents/skills/churchcrm/plugin-development.md)
- [Plugin Migration](../../.agents/skills/churchcrm/plugin-migration.md)
- [Plugin Security Scan (maintainers)](../../.agents/skills/churchcrm/plugin-security-scan.md)
- [Plugin Compliance (admins)](../../.agents/skills/churchcrm/plugin-compliance.md)
