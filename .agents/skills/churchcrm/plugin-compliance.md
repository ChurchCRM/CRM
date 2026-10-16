---
title: "Plugin Compliance — Admin Audit Guide"
intent: "Checklist a ChurchCRM admin follows to vet, audit, and scan community plugins on their own server"
tags: ["plugins","security","admin","audit","compliance"]
prereqs: ["[[plugin-system]]"]
complexity: "beginner"
---

# Plugin Compliance — Admin Audit Guide

Audience: ChurchCRM site administrators. Plugin developers and maintainers reviewing a community plugin PR use [`plugin-security-scan.md`](./plugin-security-scan.md).

For every plugin on the server, an admin can answer:

1. Is it on the approved list?
2. Do the bytes on disk match what was approved?
3. What is it allowed to do, and is that risk acceptable?

Work through every section when you install, upgrade or audit a community plugin. The commands need the ChurchCRM admin role and shell access on the server.

---

## 1. Before you install

### 1a. Confirm the plugin is on the approved list

`PluginInstaller` installs only entries in the approved registry (`CentralServices::PLUGIN_REGISTRY_URL`, loaded by `ApprovedPluginRegistry`). List it:

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/plugins/api/approved | jq
```

Each entry:

```json
{
  "id": "example-plugin",
  "name": "Example Plugin",
  "version": "1.0.0",
  "downloadUrl": "https://example.org/releases/example-plugin-1.0.0.zip",
  "sha256": "…64 hex…",
  "risk": "medium",
  "riskSummary": "Stores a MailChimp API key and POSTs member email + name to api.mailchimp.com on Person create/update hooks.",
  "permissions": ["network.outbound", "secrets.store", "hooks.person", "hooks.family"],
  "minimumCRMVersion": "7.1.0",
  "reviewedAt": "2026-04-13"
}
```

### 1b. Read the risk fields

`risk`, `riskSummary` and `permissions` are the key fields. Escalate to your team before installing when:

- `risk` is `"high"` and nobody has signed off on the `riskSummary`
- `permissions` includes `hooks.financial` or `hooks.person` together with `network.outbound` (PII or donation data can leave the server)
- `permissions` includes `fs.write` together with `network.outbound` (self-updater / supply-chain exposure; treat as high)
- `reviewedAt` is older than six months (ask maintainers for a re-review)

### 1c. Confirm the source repo is alive

Open the plugin's `homepage` and look for tagged releases in the last 12 months, a published Vulnerability Disclosure Policy or `SECURITY.md`, and a reachable issue tracker. Choose a different plugin when any is missing.

---

## 2. At install time

The admin UI calls the install API; the curl form serves break-glass audits:

```bash
curl -s -X POST \
  -H "x-api-key: $ADMIN_API_KEY" \
  -H "content-type: application/json" \
  -d '{"downloadUrl":"https://example.org/releases/example-plugin-1.0.0.zip"}' \
  https://your-crm.example.org/plugins/api/plugins/install | jq
```

`PluginInstaller` requires every check to pass:

1. The URL is in the registry exactly as published
2. The installed ChurchCRM version meets `minimumCRMVersion`
3. `src/plugins/community/{id}` does not exist yet
4. The download is at most 20 MB over HTTPS with valid TLS
5. The SHA-256 of the download matches the registry
6. The zip has no ZIP Slip, `..`, absolute paths, drive letters, control bytes or symlinks
7. Exactly one top-level directory, named `{id}`
8. No server-executable extensions (`.phar`, `.phtml`, `.sh`, `.exe`, `.so`, `.dll`, ...) or hidden files other than `.editorconfig` / `.gitattributes`
9. The extracted `plugin.json` matches `id`, `version` and `type: "community"`

When the installer rejects a plugin, file an issue against the plugin and wait for a fixed release.

The installer leaves the plugin disabled. Review `src/plugins/community/{id}/` before clicking Enable.

---

## 3. Scan an installed plugin

Run these monthly.

### 3a. Checksum drift and stray files

`src/plugins/community/` is excluded from orphan detection (`AppIntegrityService`), so any file there that the scan reports as an orphan is a security event:

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/admin/api/orphaned-files \
  | jq '.files[] | select(startswith("plugins/community/"))'
```

Output from that command means the exclusion was altered or a file escaped its plugin directory.

### 3b. File permission sweep

From the CRM document root:

```bash
find src/plugins/community -type f \
  \( -perm -o+w -o -perm -g+w \) -print
```

Strip write bits from each file listed with `chmod` and file an issue against the plugin.

### 3c. Outbound hostname review

```bash
grep -rnE "curl_init|file_get_contents\(['\"]http|Guzzle|fsockopen" \
  src/plugins/community/{id}
```

Every hostname should appear in the plugin's `riskSummary`. Disable the plugin until unrecognized hosts are explained.

### 3d. Plugin-local database writes

```bash
grep -rnE "->save\(\)|doDelete|doUpdate|PDO|mysqli" \
  src/plugins/community/{id}
```

Writes outside `$this->getConfigValue()` / `$this->setConfigValue()` need `db.write` in the `permissions` list. A plugin without it is out of compliance with its registry entry: disable it and report.

### 3e. Optional malware scan

```bash
composer global require scr34m/php-malware-scanner
~/.composer/vendor/bin/scanner src/plugins/community/{id}
```

Investigate every finding; false positives are fine once explained.

---

## 3f. Uninstalling a community plugin

Click the trash icon on the plugin card in Admin → Plugins, or:

```bash
curl -s -X DELETE \
  -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/plugins/api/plugins/{id}
```

Uninstall:

1. Applies to community plugins only; core plugins are disabled, not removed
2. Calls the plugin's `deactivate()` and `uninstall()` lifecycle hooks so it can tear down external state (webhooks, scheduled jobs)
3. Deletes `src/plugins/community/{id}/` recursively
4. Clears every `plugin.{id}.*` row from SystemConfig (credentials, enablement, settings)
5. Calls `PluginManager::reset()`

Uninstall is the upgrade path: installing an `{id}` whose directory exists is refused, so uninstall first, then reinstall.

## 3g. Quarantine

When a plugin throws during load or boot (missing dependency, syntax error, a `Throwable` from `boot()`), the runtime quarantines it. A quarantined plugin:

- Returns `false` from `isPluginActive()` even when `enabled` is `1`
- Is refused by `enablePlugin()` until an admin clears the quarantine
- Shows a yellow banner with the exception message on its card

After fixing the cause (install the dependency, upgrade, revert config), click **Clear Quarantine** (shield icon) or:

```bash
curl -s -X DELETE \
  -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/plugins/api/plugins/{id}/quarantine
```

Clearing the quarantine leaves the plugin disabled; enabling it is a separate decision.

## 3h. Unverified URL install

A plugin whose URL is not in the approved list installs through **Install from URL** (Admin → Plugins), or:

```bash
curl -s -X POST \
  -H "x-api-key: $ADMIN_API_KEY" \
  -H "content-type: application/json" \
  -d '{"downloadUrl":"https://example.org/my-plugin-0.1.0.zip","sha256":"...64hex...","pluginId":"my-plugin"}' \
  https://your-crm.example.org/plugins/api/plugins/install-url
```

Every installer safety check still applies (HTTPS, checksum before `ZipArchive` opens, ZIP Slip / extension / traversal walk, `plugin.json` cross-check). The only difference is that the URL has had no maintainer review. The admin UI marks the plugin with a yellow banner and shows no risk/permissions data. Audit an unverified plugin yourself with section 3. When its URL later appears in the registry, the installer takes the verified path and shows the full risk banner.

## 4. When a plugin leaves the registry

When maintainers remove a plugin from the registry (usually after a disclosed vulnerability):

1. Disable it immediately: `POST /plugins/api/plugins/{id}/disable`
2. Re-enable it after a fixed version is back in the registry with a new SHA-256
3. Check donation, people and email logs for what the plugin may have done, starting with the hooks in its `permissions` list; treat `hooks.financial` + `network.outbound` as a potential data leak until shown otherwise
4. Follow the release notes for replacements

---

## 5. Quarterly self-audit

- [ ] Every plugin under `src/plugins/community/` has a matching registry entry
- [ ] Every `reviewedAt` is at most 6 months old
- [ ] Sections 3a-3d re-run on every installed community plugin
- [ ] The orphan scan shows nothing under `plugins/community/`
- [ ] `src/ChurchCRM/Service/AppIntegrityService.php` still contains the `plugins/community/` exclusion; `cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js` guards it in CI

---

## 6. Where things live

- `CentralServices::PLUGIN_REGISTRY_URL` — the approved registry
- `src/plugins/community/{id}/` — extracted plugin files
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` — registry loader and validator
- `src/ChurchCRM/Plugin/PluginInstaller.php` — install-time checks
- `src/ChurchCRM/Plugin/PluginLocalization.php` — plugin-local strings
- `src/ChurchCRM/Service/AppIntegrityService.php` — orphan scan and its `plugins/community/` exclusion
- `src/plugins/routes/api/management.php` — plugin management API

---

**Related:**

- [Plugin Security Scan](./plugin-security-scan.md) — maintainer review checklist
- [Plugin System](./plugin-system.md) — runtime architecture
- [Plugin Development](./plugin-development.md) — developer guide
- [Security Best Practices](./security-best-practices.md)
