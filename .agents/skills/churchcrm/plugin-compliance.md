---
title: "Plugin Compliance — Admin Audit Guide"
intent: "Checklist a ChurchCRM admin follows to vet, audit, and scan community plugins on their own server"
tags: ["plugins","security","admin","audit","compliance"]
prereqs: ["plugin-system.md"]
complexity: "beginner"
---

# Plugin Compliance — Admin Audit Guide <!-- learned: 2026-04-13 -->

> **Audience:** ChurchCRM site administrators (not plugin developers and
> not ChurchCRM maintainers). If you are a developer submitting a plugin
> for review, read [`plugin-security-scan.md`](./plugin-security-scan.md)
> instead. If you are a ChurchCRM maintainer reviewing a community plugin
> PR, also read [`plugin-security-scan.md`](./plugin-security-scan.md).

This skill walks a site admin through the three questions they should
always be able to answer about any plugin running on their server:

1. **Is it on the approved list?**
2. **Do the bytes on disk match what was approved?**
3. **What is it allowed to do, and do I accept that risk?**

Work through every section whenever you install, upgrade, or audit a
community plugin. All of the commands below require admin role in
ChurchCRM and root shell access on the server.

---

## 1. Before you install

### 1a. Confirm the plugin is on the approved list

ChurchCRM refuses to install anything not in
`src/plugins/approved-plugins.json`, but the admin UI should also show
you the list so you can review it before clicking Install.

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/plugins/api/approved | jq
```

Expected shape for each entry:

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

### 1b. Read the risk fields out loud

`risk`, `riskSummary`, and `permissions` are the most important fields
on the entry. If any of the following is true, **stop and escalate to
the rest of your team** before installing:

- `risk` is `"high"` and you haven't signed off on the `riskSummary`.
- `permissions` includes `hooks.financial` or `hooks.person` and the
  plugin also has `network.outbound`. That combination exfiltrates PII
  or donation data off your server.
- `permissions` includes `fs.write` **and** `network.outbound`. That
  combination is the classic self-updater/supply-chain footgun and
  should be treated as high regardless of the declared level.
- `reviewedAt` is older than six months. Ask maintainers for a
  re-review before relying on it.

### 1c. Confirm the source repo is alive

Open the plugin's `homepage`. Look for:

- Tagged releases in the last 12 months.
- A published **Vulnerability Disclosure Policy (VDP)** or
  `SECURITY.md`. This is required under 2026 EU rules for commercial
  plugins and is a strong health signal for community plugins too.
- An issue tracker you can reach.

If any of those is missing, prefer not to install.

---

## 2. At install time

Install a community plugin via the admin API (only the admin UI should
call this in normal operation; the curl command below is useful for
break-glass audits):

```bash
curl -s -X POST \
  -H "x-api-key: $ADMIN_API_KEY" \
  -H "content-type: application/json" \
  -d '{"downloadUrl":"https://example.org/releases/example-plugin-1.0.0.zip"}' \
  https://your-crm.example.org/plugins/api/plugins/install | jq
```

`PluginInstaller` will refuse the request unless every one of these
checks passes, in order:

1. The URL is in `approved-plugins.json` exactly as published.
2. Your installed ChurchCRM version meets `minimumCRMVersion`.
3. The destination `src/plugins/community/{id}` does not already exist.
4. The download is ≤ 20 MB and served over HTTPS with valid TLS.
5. The downloaded bytes' SHA-256 matches the registry exactly.
6. The zip has no ZIP Slip, no `..`, no absolute paths, no drive
   letters, no control bytes, and no symlinks.
7. Exactly one top-level directory, named `{id}`.
8. No `.phar`, `.phtml`, `.sh`, `.exe`, `.so`, `.dll`, or hidden files
   except `.editorconfig` / `.gitattributes`.
9. Extracted `plugin.json` matches `id`, `version`, `type: "community"`.

If the installer rejects a plugin, **do not override it**. File an
issue against the plugin and wait for a fixed release.

The installer does **not** auto-enable the plugin. Review the extracted
files in `src/plugins/community/{id}/` before clicking Enable.

---

## 3. Compliance scan on an already-installed plugin

Run these checks on your own schedule (we recommend monthly).

### 3a. Checksum drift

Compare the on-disk zip (or re-zip of the directory) against the
`sha256` in `approved-plugins.json`.

```bash
# Example: rebuild the tarball and compare
cd src/plugins/community
tar --sort=name --mtime='UTC 2020-01-01' -czf /tmp/{id}.tgz {id}
sha256sum /tmp/{id}.tgz
# Compare against the approved sha256 — NOT the upstream release zip,
# because extraction can reorder entries. Use this as a drift signal
# only, then confirm via the next check.
```

A clearer signal: re-run the orphaned-files scan. Because
`src/plugins/community/` is excluded from orphan detection, any file
that shows up there as an orphan is actively suspicious — either the
exclusion has been tampered with, or a file has escaped its plugin
directory.

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/admin/api/orphaned-files \
  | jq '.files[] | select(startswith("plugins/community/"))'
```

Any output from that pipe is a security event.

### 3b. File permission sweep

Run from the CRM document root:

```bash
find src/plugins/community -type f \
  \( -perm -o+w -o -perm -g+w \) -print
```

Any file listed is world- or group-writable. Strip the bits with
`chmod` and file an issue against the plugin.

### 3c. Outbound hostname review

```bash
grep -rnE "curl_init|file_get_contents\(['\"]http|Guzzle|fsockopen" \
  src/plugins/community/{id}
```

Confirm every hostname you see is named in the plugin's
`riskSummary`. Hosts you don't recognise are cause to disable the
plugin until you understand them.

### 3d. Plugin-local database writes

```bash
grep -rnE "->save\(\)|doDelete|doUpdate|PDO|mysqli" \
  src/plugins/community/{id}
```

If you find writes outside the plugin's sandboxed config methods
(`$this->getConfigValue()` / `$this->setConfigValue()`), verify the
`permissions` list includes `db.write`. If it doesn't, the plugin is
out of compliance with its registry entry — disable it and report.

### 3e. Optional: run `php-malware-scanner`

```bash
composer global require scr34m/php-malware-scanner
~/.composer/vendor/bin/scanner src/plugins/community/{id}
```

Investigate every finding. False positives are fine; silence is fine;
unexplained hits are not.

---

## 4. When a plugin is dropped from the registry

If ChurchCRM maintainers remove a plugin from `approved-plugins.json`
(usually because a vulnerability was disclosed):

1. **Disable it immediately** — `POST /plugins/api/plugins/{id}/disable`.
2. **Do not re-enable it** until a fixed version is added back to the
   registry with a new SHA-256.
3. **Check your donation/people/email logs** for anything the plugin
   may have already done. Start with the hooks in its `permissions`
   list — a plugin with `hooks.financial` + `network.outbound` should
   be treated as a potential data leak until proven otherwise.
4. **Subscribe to the release notes** so you learn about replacements.

---

## 5. Quarterly self-audit

Put this in your calendar. Every quarter:

- [ ] Confirm every plugin under `src/plugins/community/` still has
      a matching entry in `approved-plugins.json`.
- [ ] Confirm every `reviewedAt` is ≤ 6 months old.
- [ ] Re-run sections 3a–3d on every installed community plugin.
- [ ] Re-run the orphan scan and confirm it is empty under
      `plugins/community/`.
- [ ] Verify the orphan-scan exclusion itself is still present by
      grepping `src/ChurchCRM/Service/AppIntegrityService.php` for
      `plugins/community/` — someone refactoring could accidentally
      delete it, and the Cypress regression test
      `cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`
      is what catches that in CI.

---

## 6. Where things live

- `src/plugins/approved-plugins.json` — the allowlist you audit against.
- `src/plugins/community/{id}/` — extracted plugin files on disk.
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` — loader/validator.
- `src/ChurchCRM/Plugin/PluginInstaller.php` — install-time checks.
- `src/ChurchCRM/Plugin/PluginLocalization.php` — plugin-local strings.
- `src/ChurchCRM/Service/AppIntegrityService.php` — orphan scan (with
  the `^plugins/community/` exclusion).
- `cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`
  — CI regression test guarding the exclusion.

---

**Related skills:**

- [Plugin Security Scan](./plugin-security-scan.md) — maintainer review checklist
- [Plugin System](./plugin-system.md) — runtime architecture
- [Plugin Development](./plugin-development.md) — developer guide
- [Security Best Practices](./security-best-practices.md)
