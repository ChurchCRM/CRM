<!-- staged: 2026-04-13 — commit PR#8657 — destined for docs.churchcrm.io wiki/plugins/plugin-security-and-compliance.md -->

# Plugin Security & Compliance

Community plugins are code you did not write, running on the same
server that holds your parish's member directory, donations, and
emails. ChurchCRM treats that as a supply-chain surface and builds the
plugin system around a simple idea: **every plugin is only as
trustworthy as the allowlist entry it was installed from**.

This page explains:

- How to read the risk level and permission tags that appear on the
  install screen.
- What the ChurchCRM maintainers checked before approving a plugin.
- How to audit a plugin that's already running on your server.
- What to do when a plugin is revoked.

---

## The approved-plugins list

ChurchCRM refuses to install community plugins that aren't listed in a
maintained allowlist (`src/plugins/approved-plugins.json` inside the
ChurchCRM codebase). Every entry in that list must declare:

| Field | What it tells you |
|-------|-------------------|
| `id`, `name`, `version` | Which plugin this is |
| `downloadUrl` | The exact HTTPS URL the zip lives at |
| `sha256` | The SHA-256 of the zip bytes the maintainers reviewed |
| `risk` | `low`, `medium`, or `high` |
| `riskSummary` | One plain-language sentence admins see before install |
| `permissions` | Capability tags the plugin declared |
| `minimumCRMVersion` | The minimum ChurchCRM version that can run it |
| `reviewedAt` | The date the maintainers reviewed the zip |

If a plugin's behaviour changes (it starts making a new outbound call,
it subscribes to a new hook, it ships a new binary), the maintainers
issue a new entry with a new `sha256` and a fresh `reviewedAt` date.
**Every version is reviewed from scratch** — an older version's
review never carries forward.

---

## Risk levels

The `risk` field is a three-level rubric:

| Risk | The plugin can… |
|------|-----------------|
| **low** | Read its own config, render UI, and optionally call a single documented outbound API with no personally identifiable information. No database writes outside plugin config, no filesystem writes outside its own directory, no PII reaching third parties. Examples: Gravatar, Google Analytics. |
| **medium** | Write to the ChurchCRM database, store credentials, send email or SMS, or react to people/family/email hooks — but only in ways that stay on-server. Examples: MailChimp, Vonage. |
| **high** | Send PII or financial data to a third party, react to donation hooks and call outbound APIs, expose its own HTTP routes, or ship binary blobs. High-risk plugins are reviewed by two maintainers before approval and should also be reviewed by you before you click Install. |

Two specific combinations always escalate to **high** regardless of
how the plugin is described:

- `fs.write` + `network.outbound` — classic self-updater/supply-chain
  footgun.
- `hooks.financial` + `network.outbound` — donation data exfiltration.

If you see either combination in the `permissions` list for a plugin
described as low or medium, **stop and ask on the forum** before
installing.

---

## Permission tags

The `permissions` array uses a controlled vocabulary. Each tag maps to
a specific capability the plugin exercises:

| Tag | Meaning |
|-----|---------|
| `network.outbound` | Plugin makes outbound HTTP(S) calls |
| `network.inbound` | Plugin exposes new HTTP routes |
| `db.read` | Plugin reads from ChurchCRM tables |
| `db.write` | Plugin writes to ChurchCRM tables |
| `fs.read` | Plugin reads files outside its own directory |
| `fs.write` | Plugin writes files outside its own directory |
| `secrets.store` | Plugin stores credentials or API keys in its config |
| `ui.inject` | Plugin injects HTML/JS/CSS into core pages |
| `cron` | Plugin runs on a schedule |
| `hooks.person` | Plugin listens for person-related events |
| `hooks.family` | Plugin listens for family-related events |
| `hooks.financial` | Plugin listens for donation/deposit events |
| `hooks.email` | Plugin listens for email events |
| `email.send` | Plugin sends email on your behalf |
| `sms.send` | Plugin sends SMS on your behalf |

Anything the plugin does that's not on this list is a policy violation
and the plugin will not be approved. The list is intentionally short
so reviewers have to think carefully before adding new categories.

---

## What the maintainers check before approving

Maintainers run through the `plugin-security-scan.md` checklist before
adding any entry to `approved-plugins.json`. In summary:

1. **Provenance.** Release artifact from a tagged release on an
   immutable HTTPS URL. Reproducible SHA-256.
2. **Archive structure.** Single top-level directory, no ZIP Slip,
   no disallowed extensions, no symlinks, no binary blobs.
3. **Static analysis.** Ripgrep for `eval`, `assert`, `create_function`,
   `shell_exec`, `passthru`, `proc_open`, `pcntl_exec`, `extract($_`,
   `unserialize`, `base64_decode` on bundled blobs; PHPStan at level 6;
   Psalm with taint analysis. Every hit must have a documented reason.
4. **Network / DB / filesystem inventory.** Every outbound host must
   be named in the risk summary, every database write must map to the
   plugin's own sandbox, every filesystem write must stay inside the
   plugin directory.
5. **Permission/capability inventory.** Every capability tag the
   plugin exercises is declared in `permissions`. Anything not
   declared is grounds for rejection.
6. **Runtime smoke test.** Install into a Docker CRM instance, enable,
   exercise every menu item and hook, check logs, confirm the
   orphan-file scan still excludes `plugins/community/`.
7. **Ongoing maintenance.** Subscribe to upstream security advisories
   and re-review on every version bump, even patch releases.

The full checklist is in
[`plugin-security-scan.md`](https://github.com/ChurchCRM/CRM/blob/main/.agents/skills/churchcrm/plugin-security-scan.md).

---

## Compliance scan

Run this on your own schedule. We recommend a monthly pass on every
installed plugin and a quarterly deep scan of the whole
`src/plugins/community/` tree.

### Verify the orphan scan is clean

Community plugins are excluded from the ChurchCRM orphan-files
scanner. Anything that shows up under `plugins/community/` in the
scan is actively suspicious:

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/admin/api/orphaned-files \
  | jq '.files[] | select(startswith("plugins/community/"))'
```

Any output from that pipe is a security event — either the exclusion
has been tampered with or a file has escaped a plugin directory.

### File permission sweep

```bash
find src/plugins/community -type f \
  \( -perm -o+w -o -perm -g+w \) -print
```

Nothing should be world- or group-writable. Strip the bits with
`chmod` and report to the plugin's issue tracker.

### Outbound hostname review

```bash
grep -rnE "curl_init|file_get_contents\(['\"]http|Guzzle|fsockopen" \
  src/plugins/community/{plugin-id}
```

Every hostname you find must be named in the plugin's `riskSummary`.
Anything else is worth investigating.

### Database write audit

```bash
grep -rnE "->save\(\)|doDelete|doUpdate|PDO|mysqli" \
  src/plugins/community/{plugin-id}
```

Writes outside the sandboxed `getConfigValue` / `setConfigValue`
helpers must correspond to a `db.write` tag in the plugin's approved
entry. If the tag is missing, the plugin is out of compliance.

### Optional: malware scanner

```bash
composer global require scr34m/php-malware-scanner
~/.composer/vendor/bin/scanner src/plugins/community/{plugin-id}
```

Investigate every finding.

---

## When a plugin is revoked

If ChurchCRM maintainers drop a plugin from `approved-plugins.json`,
it usually means a vulnerability was disclosed. Your server does not
automatically roll back — the old files are still on disk — so you
need to take action:

1. **Disable the plugin immediately.** `POST
   /plugins/api/plugins/{id}/disable` or click Disable in
   **Admin → Plugins**.
2. **Do not re-enable it** until a fixed version is added back to the
   registry with a new SHA-256.
3. **Check the relevant logs.** If the plugin had
   `hooks.financial` + `network.outbound`, treat it as a potential
   data leak until proven otherwise. Look at your donation ledger,
   email queue, and access logs for anything unusual.
4. **Subscribe to the release notes** so you learn about replacements.

---

## Quarterly audit checklist

- [ ] Every plugin under `src/plugins/community/` still appears in
      `approved-plugins.json`.
- [ ] Every `reviewedAt` is six months old or less.
- [ ] The orphan scan returns zero entries under `plugins/community/`.
- [ ] The orphan-scan exclusion is still present in
      `src/ChurchCRM/Service/AppIntegrityService.php`.
- [ ] No files in `src/plugins/community/` are world-writable.
- [ ] Outbound hosts match the declared risk summaries.
- [ ] Database writes match the declared `db.write` tag.
- [ ] You have a backup less than seven days old.
