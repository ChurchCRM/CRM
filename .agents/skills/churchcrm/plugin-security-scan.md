---
title: "Plugin Security Scan"
intent: "Required review checklist before approving a community plugin for approved-plugins.json — intake, static analysis, risk classification"
tags: ["plugins", "security", "audit", "compliance"]
prereqs: ["[[plugin-system]]"]
complexity: "intermediate"
---
# Plugin Security Scan

Use this skill every time a community plugin is proposed for the approved registry. It vets a plugin zip before it is published to end users. A plugin enters the registry after every section below is signed off and documented in the PR.

`PluginInstaller::installFromUrl` installs only entries in the registry, so the registry is the supply-chain allowlist between third-party code and a production server. The registry file `approved-plugins.json` lives on the `External` branch of `ChurchCRM/CRM` and is read through `CentralServices::PLUGIN_REGISTRY_URL` by `ApprovedPluginRegistry`.

---

## Scope

Run this scan when you:

- Propose a new registry entry
- Bump an existing entry to a new version (every version is scanned fresh)
- Respond to a reported vulnerability in an approved plugin
- Audit an existing entry on the quarterly cadence

---

## Before starting

- [ ] The exact HTTPS zip URL the maintainer wants published
- [ ] A throwaway working directory (unpack candidate zips outside the CRM tree)
- [ ] PHP 8.2+ with `composer`, `phpstan` and `psalm`
- [ ] `ripgrep`, `shasum` and `unzip` on PATH
- [ ] The plugin author's contact and Vulnerability Disclosure Policy (VDP)

---

## 1. Intake and supply chain

1. **Pin the bytes.** Download once with `curl -fL -o plugin.zip` and compute `shasum -a 256 plugin.zip`. That hash goes into the registry entry, and the upstream URL must serve those bytes from review to publish.
2. **HTTPS origins you can verify.** Use GitHub or GitLab release artifacts or the maintainer's own TLS domain. Shorteners, pastebins, gists and transient upload buckets fail review.
3. **Immutable release URL.** The URL addresses a tagged release, never `main`.
4. **Provenance.** Confirm the upstream maintainer tagged the release (git tag signature, GitHub actor matches the repo owner, or PGP signature).
5. **Review date.** Set `reviewedAt` (ISO-8601) on the entry.

---

## 2. Zip structure

```bash
unzip -l plugin.zip   # list without extracting
```

Reject the plugin when:

- Any entry begins with `/` or `..`, starts with a Windows drive letter or contains control characters (`PluginInstaller::assertSafeZipEntry` also blocks these at install)
- It has more than one top-level directory, or the top-level name differs from the `id` in `plugin.json`
- It contains `.phar`, `.phtml`, `.pht`, `.sh`, `.exe`, `.so`, `.dll`, symlinks, setuid bits, `.git/`, `node_modules/`, `vendor/` with unvetted dependencies, or unexplained binary blobs
- Uncompressed size exceeds about 80 MB or the entry count exceeds about 2000 (zip bomb or bundled framework)
- Files carry world-writable (`0777`) or setuid/setgid bits (strip and re-archive first)

`{id}/plugin.json` exists and declares:

- `"type": "community"`
- `id`, `name`, `version`, `author`, `minimumCRMVersion`, `mainClass` (matching the real PHP namespace)
- Accurate `dependencies` and `settings` schemas

---

## 3. Static analysis

Unpack into a sandbox (`/tmp/plugin-review/{id}`) and run:

```bash
# Dangerous PHP sinks
rg -n --no-heading \
  'eval\(|assert\(|create_function|preg_replace.*\/e|include[[:space:]]*\$|require[[:space:]]*\$|`.*\$|popen|passthru|shell_exec|proc_open|system\(|pcntl_exec|extract\(\$_|parse_str\(\$_|unserialize\(' \
  .

# Remote calls (justify every outbound URL in the PR description)
rg -n 'curl_init|file_get_contents\([\'"]http|fopen\([\'"]http|fsockopen|stream_socket_client|Guzzle|Symfony\\HttpClient'

# Filesystem reach (paths outside the plugin directory are suspicious)
rg -n 'file_put_contents|fwrite|unlink|rename|copy|chmod|chown|mkdir|rmdir|move_uploaded_file'

# DB access outside the sandboxed config API
rg -n 'PDO|mysqli|Propel|->save\(\)|doDelete|doUpdate'

# Obfuscation / packed payloads
rg -n 'base64_decode|gzinflate|gzuncompress|str_rot13|chr\([0-9]+\)\s*\.'
```

Classify every hit:

- **OK**: expected behavior documented in the README/manifest
- **Needs hardening**: contact the author before approving
- **Block**: reject the submission

Then:

```bash
php -l $(find . -name "*.php")
phpstan analyse --level=6 .
psalm --taint-analysis --no-cache
```

Taint analysis clears on every path touching `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`, request bodies or plugin settings.

Run [scr34m/php-malware-scanner](https://github.com/scr34m/php-malware-scanner) as an extra static pass.

---

## 4. Permission inventory

Put a table in the PR body with one row per capability tag in `ApprovedPluginRegistry::KNOWN_PERMISSIONS` (read the constant for the current tags). Columns: tag, used (yes/no), evidence (file:line), notes. A capability the plugin uses that no tag covers is grounds for rejection, or for extending `KNOWN_PERMISSIONS` in the same PR.

### 4a. Risk classification

Every entry sets `risk` (`low` | `medium` | `high`) and a one-sentence `riskSummary`. The install screen shows both to admins before Install.

| Risk | Use when the plugin... |
|------|------------------------|
| **low** | Reads config, renders UI, optionally calls one documented outbound endpoint with non-PII data. No DB writes, no filesystem writes outside its own directory, no secrets beyond its own plugin config, no hooks touching PII. Examples: Gravatar, Google Analytics |
| **medium** | Exercises `db.write`, `fs.write`, `secrets.store`, `email.send`, `sms.send`, or listens on `hooks.person` / `hooks.family` / `hooks.email` without exporting PII off-server. Examples: MailChimp, Vonage |
| **high** | Sends PII or financial data over `network.outbound`, combines `hooks.financial` + `network.outbound`, combines `cron` + `fs.write`, exposes `network.inbound` routes that accept unauthenticated requests, or ships binary blobs. Needs two maintainer reviews before merging |

Mandatory uplift: `fs.write` + `network.outbound`, or `hooks.financial` + `network.outbound`, is **high** whatever the diff size (exfiltration and supply-chain exposure).

Write `riskSummary` in plain language:

- low: "Uses Gravatar's public image API to fetch profile photos. No PII leaves the server."
- medium: "Stores a MailChimp API key and POSTs member email + name to api.mailchimp.com on Person create/update hooks."
- high: "Subscribes to DONATION_RECEIVED and POSTs donor name, email, and amount to a third-party accounting endpoint."

---

## 5. Runtime smoke test

1. Start a Docker CRM instance (`npm run build && docker compose up`)
2. Install the candidate through the unverified-URL path, `POST /plugins/api/plugins/install-url` with `downloadUrl`, `sha256` and `pluginId` (the URL is not in the registry yet)
3. Enable the plugin and watch `logs/app.log` for warnings
4. Exercise every menu item, hook and route the plugin declares
5. Disable and re-enable; the only `config_cfg` rows are `plugin.{id}.*`
6. Confirm `AppIntegrityService::getOrphanedFiles()` does not report the plugin (`plugins/community/` is excluded from the scan)
7. Remove the plugin directory and confirm the CRM boots

Record the run in the PR description.

---

## 6. Approval and publishing

After every section is green:

1. Add the entry to `approved-plugins.json` on the `External` branch:

   ```json
   {
     "id": "example-plugin",
     "name": "Example Plugin",
     "version": "1.0.0",
     "downloadUrl": "https://example.org/releases/example-plugin-1.0.0.zip",
     "sha256": "<64-hex sha256>",
     "risk": "medium",
     "riskSummary": "Stores a MailChimp API key and POSTs member email + name to api.mailchimp.com on Person create/update hooks.",
     "permissions": [
       "network.outbound",
       "secrets.store",
       "hooks.person",
       "hooks.family"
     ],
     "minimumCRMVersion": "7.1.0",
     "author": "Example Author",
     "homepage": "https://example.org/",
     "reviewedAt": "2026-04-13",
     "notes": "Outbound calls only to api.mailchimp.com over HTTPS. API key stored in plugin.example-plugin.apiKey. No DB writes outside plugin config."
   }
   ```

   Required keys: `id`, `name`, `version`, `downloadUrl`, `sha256`, `risk`, `riskSummary`. `permissions` is optional and recommended; `ApprovedPluginRegistry` rejects an entry with an unknown tag.

2. Open a PR titled `plugins: approve example-plugin@1.0.0`
3. Paste the capability table and static-analysis summary into the PR body
4. Merge with a second reviewer's sign-off

---

## 7. Ongoing maintenance

- Follow the upstream repo's releases and advisories
- Re-run sections 2-5 for every version bump, patch releases included
- When a plugin turns out vulnerable, drop its entry at once and add a changelog entry; the installer then refuses new installs and the admin UI flags installed copies as unverified
- Each quarter, re-verify or retire entries whose `reviewedAt` is older than 6 months
- Prefer plugins whose releases are signed with a stable key, and record the key fingerprint in the PR description
- Require a published VDP or `SECURITY.md` before approval

---

## 8. Code references

- `CentralServices::PLUGIN_REGISTRY_URL` — the allowlist location
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` — loader and validation
- `src/ChurchCRM/Plugin/PluginInstaller.php` — download/verify/extract pipeline; most checks above are enforced here too
- `src/plugins/routes/api/management.php` — `GET /plugins/api/approved`, `POST /plugins/api/plugins/install`, `POST /plugins/api/plugins/install-url`
- `src/ChurchCRM/Service/AppIntegrityService.php::isExcludedFromOrphanDetection` — keeps community plugins out of the orphan scan
- `scripts/generate-signatures-node.js` — the matching exclusion for the build-time signatures manifest

Keep install-time enforcement and this review checklist in sync: a new class of check in one updates the other.
