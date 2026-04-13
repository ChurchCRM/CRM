# Plugin Security Scan <!-- learned: 2026-04-13 -->

Use this skill every time a new community plugin is proposed for
`src/plugins/approved-plugins.json`. It walks you through vetting a plugin
zip before it is published to end users. No plugin enters the approved list
until every section below is signed off and documented in the PR.

> **Why this exists:** ChurchCRM now supports URL-based plugin installs
> (see `PluginInstaller::installFromUrl`). The installer refuses anything
> that isn't in `approved-plugins.json`, so that file is the only supply
> chain gate between third-party code and a production parish server. Treat
> it like an allowlist, not a registry.

---

## Scope

Run this scan whenever you:

- Propose a new entry for `src/plugins/approved-plugins.json`.
- Bump an existing entry to a new version (every version is scanned fresh —
  the previous review does not carry over).
- Respond to a reported vulnerability in an already-approved plugin.
- Audit an existing entry on the quarterly review cadence.

---

## Fixtures you need before starting

- [ ] The exact zip URL the maintainer wants published (HTTPS only).
- [ ] A clean working copy in a throwaway directory — never unpack a
      candidate zip inside the CRM tree.
- [ ] PHP 8.2+ with `composer`, `phpstan`, and `psalm` available.
- [ ] `ripgrep`, `shasum`, and `unzip` on PATH.
- [ ] The plugin author's contact + vulnerability disclosure policy (VDP).
      WordPress-style marketplaces will require a VDP for every commercial
      plugin in 2026; we enforce the same bar.

---

## 1. Intake & supply chain checks

1. **Pin the bytes.** Download the zip once with `curl -fL -o plugin.zip`
   and compute the SHA-256: `shasum -a 256 plugin.zip`. That hash is what
   goes into `approved-plugins.json`; do **not** let the upstream overwrite
   it between review and publish.
2. **Reject non-HTTPS** URLs, URL shorteners, and mirrors whose origin you
   cannot verify (pastebin, gists, transient CDN upload buckets).
3. **Require reproducible hosting.** GitHub release artifacts, GitLab
   release artifacts, or the maintainer's own domain with TLS are OK.
   "Whatever is in `main` right now" is **not** acceptable — the URL must
   address an immutable release.
4. **Validate provenance.** Confirm the release was tagged by the upstream
   maintainer you trust (git tag signature, GitHub actor matches the repo
   owner, or PGP signature).
5. **Record a review date.** Add `reviewedAt` (ISO-8601) to the registry
   entry.

---

## 2. Zip structure checks

Run these before unpacking into a sandbox:

```bash
unzip -l plugin.zip   # list contents without extracting
```

Reject the plugin if any of the following are true:

- Any entry begins with `/`, `..`, a Windows drive letter, or contains
  control characters. (`PluginInstaller::assertSafeZipEntry` will block
  these at install time — we also reject them at review time.)
- There is more than one top-level directory, or the top-level name does
  not match the `id` in `plugin.json`.
- The archive contains any of: `.phar`, `.phtml`, `.pht`, `.sh`, `.exe`,
  `.so`, `.dll`, symlinks, setuid bits, `.git/`, `node_modules/`,
  `vendor/` with unvetted dependencies, binary blobs you cannot explain.
- Uncompressed size exceeds ~80 MB or entry count exceeds ~2000 (possible
  zip bomb or bundled framework).
- Files carry world-writable (`0777`) or setuid/setgid bits — strip and
  re-archive before approving.

Manifest must exist at `{id}/plugin.json` and must declare:

- `"type": "community"` (URL installs refuse anything else).
- `id`, `name`, `version`, `author`, `minimumCRMVersion`, `mainClass`
  (matches the actual PHP namespace).
- Accurate `dependencies` and `settings` schemas.

---

## 3. Static analysis

Unpack into a sandbox (`/tmp/plugin-review/{id}`) and run:

```bash
# Dangerous PHP sinks
rg -n --no-heading \
  'eval\(|assert\(|create_function|preg_replace.*\/e|include[[:space:]]*\$|require[[:space:]]*\$|`.*\$|popen|passthru|shell_exec|proc_open|system\(|pcntl_exec|extract\(\$_|parse_str\(\$_|unserialize\(' \
  .

# Remote calls (every outbound URL must be justified in the PR description)
rg -n 'curl_init|file_get_contents\([\'"]http|fopen\([\'"]http|fsockopen|stream_socket_client|Guzzle|Symfony\\HttpClient'

# Filesystem reach (paths outside the plugin directory are suspicious)
rg -n 'file_put_contents|fwrite|unlink|rename|copy|chmod|chown|mkdir|rmdir|move_uploaded_file'

# DB access outside the sandboxed config API
rg -n 'PDO|mysqli|Propel|->save\(\)|doDelete|doUpdate'

# Obfuscation / packed payloads
rg -n 'base64_decode|gzinflate|gzuncompress|str_rot13|chr\([0-9]+\)\s*\.'
```

Every hit needs an explanation. Categorise each into:

- **OK** — expected behaviour documented in README/manifest.
- **Needs hardening** — reach out to the author before approving.
- **Block** — reject the submission.

Then run:

```bash
php -l $(find . -name "*.php")
phpstan analyse --level=6 .
psalm --taint-analysis --no-cache
```

Taint analysis must clear on any path that touches `$_GET`, `$_POST`,
`$_REQUEST`, `$_SERVER`, request bodies, or plugin settings.

---

## 4. Permission / capability inventory

Produce a short table for the PR body:

| Capability               | Evidence (file:line) | Notes |
|--------------------------|----------------------|-------|
| Filesystem writes        |                      |       |
| Outbound HTTP(S)         |                      |       |
| DB writes                |                      |       |
| Background / cron hooks  |                      |       |
| UI injection points      |                      |       |
| Required permissions     |                      |       |
| Secrets stored in config |                      |       |

This matches the WordPress 2026 "declared capabilities" model and the
upcoming plugin-security validation in WP 7.2. Any capability the plugin
uses that is not listed in the table is grounds for rejection.

The entry in `approved-plugins.json` should summarise these in the `notes`
field so end users can see what they're granting before they click Install.

---

## 5. Runtime smoke test

1. Spin up a Docker CRM instance (`npm run build && docker compose up`).
2. Install the plugin via `POST /plugins/api/plugins/install` with the
   candidate URL. The installer will reject it because it is not yet in
   the registry — temporarily add the entry to a local copy of
   `approved-plugins.json` for the test only.
3. Enable the plugin. Watch `logs/app.log` for warnings.
4. Exercise every menu item, hook, and route the plugin declares.
5. Disable + re-enable. Confirm no orphan rows in `config_cfg` beyond
   `plugin.{id}.*` (those are expected).
6. Confirm `AppIntegrityService::getOrphanedFiles()` does **not** report
   the plugin — `plugins/community/` is now excluded from the scan by
   design, and this check verifies the exclusion still works.
7. Remove the plugin directory and confirm the CRM still boots.

Record the test run in the PR description.

---

## 6. Approval & publishing

Only after every section above is green:

1. Add the entry to `src/plugins/approved-plugins.json`:

   ```json
   {
     "id": "example-plugin",
     "name": "Example Plugin",
     "version": "1.0.0",
     "downloadUrl": "https://example.org/releases/example-plugin-1.0.0.zip",
     "sha256": "<64-hex sha256>",
     "minimumCRMVersion": "7.1.0",
     "author": "Example Author",
     "homepage": "https://example.org/",
     "reviewedAt": "2026-04-13",
     "notes": "Filesystem: none. Network: POSTs donations to example.org/api. No DB writes outside plugin config."
   }
   ```

2. Open a PR titled `plugins: approve example-plugin@1.0.0`.
3. Paste the capability table and the static-analysis summary into the PR
   body. CI will re-verify the manifest shape.
4. Merge only with a second reviewer's sign-off.

---

## 7. Ongoing maintenance

- Subscribe to the upstream repo's releases and advisories.
- Re-run steps 2–5 for every version bump — even patch releases.
- If a plugin is found vulnerable, drop its entry immediately and publish
  a changelog entry. The installer will then refuse further installs, and
  the admin UI will flag already-installed copies as unverified.
- Quarterly, sweep `approved-plugins.json` for entries whose `reviewedAt`
  is older than 6 months. Re-verify or retire them.

---

## 8. 2026 plugin standards reference

The review bar above is informed by the plugin security standards landscape
for 2026:

- **WordPress Plugin Directory** still requires a zip submission, manual
  code review, and permanent shutdown of any plugin found to have a
  security issue until it is resolved. [Developer handbook — detailed plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
- **EU VDP law (2026)** — every commercial plugin distributed to EU users
  must publish a Vulnerability Disclosure Program. We apply the same
  requirement to community plugins before approval.
- **WordPress 7.2 (Dec 2026)** introduces enhanced plugin-security
  validation and supply-chain protection; baseline SHA-256 manifests stored
  at install time are the canonical defence. See the [State of WordPress Security in 2026 whitepaper](https://patchstack.com/whitepaper/state-of-wordpress-security-in-2026/).
- **Baseline checksums** — every installed plugin keeps a SHA-256 of its
  zip (that is the `sha256` column in `approved-plugins.json`). Before a
  privileged operation the agent/host should compare the current on-disk
  checksums against that baseline; a mismatch means the plugin has been
  tampered with. See the [2026 plugin security audit guide](https://blog.webhostmost.com/wordpress-plugin-security-audit-guide-2026/).
- **Manifest scanning** — plugin.json and plugin.yaml are now scanned for
  prompt-injection signatures, role-override strings, hidden Unicode, and
  suspicious permission combinations (filesystem + network outbound is a
  classic red flag). See the [OpenClaw security audit write-up](https://www.sitepoint.com/openclaw-security-audit-detecting-malicious-ai-agent-plugins/).
- **Signed updates** — prefer plugins whose releases are signed with a
  stable key. Record the key fingerprint in the PR description. See the
  [WordPress plugin security best practices guide (2026)](https://xtnd.net/blog/wordpress-plugin-security-best-practices/).
- **php-malware-scanner** — run [scr34m/php-malware-scanner](https://github.com/scr34m/php-malware-scanner)
  against every candidate plugin as an extra static pass.

---

## 9. Code references

- `src/plugins/approved-plugins.json` — the allowlist.
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` — loader + validation.
- `src/ChurchCRM/Plugin/PluginInstaller.php` — the download/verify/extract
  pipeline. Read this before changing the review process, because most of
  the checks above are enforced there too.
- `src/plugins/routes/api/management.php` — `GET /plugins/api/approved`
  and `POST /plugins/api/plugins/install`.
- `src/ChurchCRM/Service/AppIntegrityService.php::isExcludedFromOrphanDetection`
  — the exclusion that keeps community plugins out of the orphan scan.
- `scripts/generate-signatures-node.js` — the matching exclusion for the
  build-time signatures manifest.

Keep the install-time enforcement and the review-time checklist in sync.
If you add a new class of check to either one, update the other.
